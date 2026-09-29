<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Tests\Integration;

use AlexandreBulete\DddFoundation\Application\Command\CommandBusInterface;
use AlexandreBulete\DddOutboxBundle\Application\Command\RetryJob\RetryJobCommand;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobId;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Doctrine\Migrations\Version20261002140000;
use AlexandreBulete\DddOutboxBundle\Tests\Integration\App\NotifyCustomer;
use AlexandreBulete\DddOutboxBundle\Tests\Integration\App\NotifyCustomerHandler;
use AlexandreBulete\DddOutboxBundle\Tests\Integration\App\PlaceOrder;
use AlexandreBulete\DddOutboxBundle\Tests\Integration\App\Thing;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * ADR 0010 on real Doctrine transports: nothing lost, nothing phantom, every
 * message followed — and a failure never freezes the queue.
 */
final class OutboxTest extends KernelTestCase
{
    private const JOBS = 'outbox_test_job';
    private const MESSAGES = 'outbox_test_messages';

    private Connection $connection;
    private CommandBusInterface $commands;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        NotifyCustomerHandler::$recovered = false;
        $container = self::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->connection = $em->getConnection();

        $commands = $container->get('test.command_bus');
        self::assertInstanceOf(CommandBusInterface::class, $commands);
        $this->commands = $commands;

        // Fresh tables, before any test transaction (MySQL commits on DDL):
        // the follow-up from the bundle's own migration; the test entity and
        // the transport's table (Messenger adds it to the ORM schema) from the
        // SchemaTool.
        $schemaManager = $this->connection->createSchemaManager();
        foreach ($schemaManager->listTableNames() as $table) {
            $schemaManager->dropTable($table);
        }
        $from = $schemaManager->introspectSchema();
        $to = clone $from;
        (new Version20261002140000($this->connection, new NullLogger(), self::JOBS))->up($to);
        foreach ($this->connection->getDatabasePlatform()->getAlterSchemaSQL($schemaManager->createComparator()->compareSchemas($from, $to)) as $sql) {
            $this->connection->executeStatement($sql);
        }
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Thing::class)]);
    }

    #[Test]
    public function the_migration_creates_exactly_the_mapped_table(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        self::assertSame([], (new SchemaTool($em))->getUpdateSchemaSql($em->getMetadataFactory()->getAllMetadata()));
    }

    #[Test]
    public function a_message_leaves_with_the_action_that_sends_it(): void
    {
        $this->commands->dispatch(new PlaceOrder());

        $job = $this->onlyJob();
        self::assertSame('queued', $job['status']);
        self::assertSame(NotifyCustomer::class, $job['message']);
        self::assertSame('async', $job['transport']);
        self::assertSame('system', $job['actor_kind']);
        self::assertNotNull($job['causation_id'], 'sent while PlaceOrder was handled: it is its effect');
        self::assertSame(1, $this->waiting('async'));
    }

    #[Test]
    public function an_action_rolled_back_sends_nothing(): void
    {
        try {
            $this->commands->dispatch(new PlaceOrder(thenFail: true));
            self::fail('The action should fail.');
        } catch (\DomainException) {
        }

        self::assertSame(0, $this->rowsIn(self::JOBS));
        self::assertSame(0, $this->waiting('async'), 'no phantom effect');
    }

    #[Test]
    public function a_consumed_job_succeeds(): void
    {
        $this->commands->dispatch(new PlaceOrder());

        $this->consume(1);

        $job = $this->onlyJob();
        self::assertSame('succeeded', $job['status']);
        self::assertSame(1, self::attempts($job));
        self::assertNotNull($job['finished_at']);
    }

    #[Test]
    public function a_failing_job_is_retried_then_parked_then_retried_by_hand(): void
    {
        $this->commands->dispatch(new PlaceOrder('flaky'));

        $this->consume(1);
        $job = $this->onlyJob();
        self::assertSame('retrying', $job['status']);
        self::assertSame(1, self::attempts($job));
        self::assertIsString($job['last_error']);
        self::assertStringContainsString('RuntimeException: Mail server down.', $job['last_error']);

        $this->consume(1);
        $job = $this->onlyJob();
        self::assertSame('failed', $job['status']);
        self::assertSame(2, self::attempts($job));
        self::assertSame(1, $this->waiting('failed'));

        NotifyCustomerHandler::$recovered = true;
        $this->commands->dispatch(new RetryJobCommand(self::jobId($job)));

        self::assertSame('queued', $this->onlyJob()['status']);
        self::assertSame(0, $this->waiting('failed'));
        self::assertSame(1, $this->waiting('async'));

        $this->consume(1);
        $job = $this->onlyJob();
        self::assertSame('succeeded', $job['status']);
        self::assertSame(3, self::attempts($job), 'the history of attempts stays');
    }

    #[Test]
    public function only_a_failed_job_is_retried_by_hand(): void
    {
        $this->commands->dispatch(new PlaceOrder());

        try {
            $this->commands->dispatch(new RetryJobCommand(self::jobId($this->onlyJob())));
            self::fail('A queued job cannot be retried.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('this one is queued', $e->getMessage());
        }

        self::assertSame(1, $this->waiting('async'), 'not sent twice');
    }

    #[Test]
    public function sending_after_the_commit_is_refused(): void
    {
        $bus = self::getContainer()->get('command.bus');
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('after the commit');
        $bus->dispatch(new NotifyCustomer(), [new DispatchAfterCurrentBusStamp()]);
    }

    /**
     * The mb-fid incident: a message that closes the EntityManager must not
     * take the next ones down. Symfony guarantees it — the worker resets
     * services between messages, and Doctrine's registry then replaces a
     * closed EntityManager — this test keeps it guaranteed.
     */
    #[Test]
    public function a_poisoned_entity_manager_does_not_freeze_the_queue(): void
    {
        $this->commands->dispatch(new PlaceOrder('poison'));
        $this->commands->dispatch(new PlaceOrder('orm'));

        // poison, orm, poison again (its one retry).
        $this->consume(3);

        $statuses = [];
        foreach ($this->jobs() as $job) {
            $statuses[] = $job['status'];
        }
        self::assertSame(['failed', 'succeeded'], $statuses, 'the message after the poisoned one still runs');
        self::assertSame(1, $this->rowsIn('outbox_test_thing'));
    }

    /**
     * @return list<array<string, mixed>> oldest first
     */
    private function jobs(): array
    {
        /** @var list<array<string, mixed>> */
        return $this->connection->fetchAllAssociative(sprintf('SELECT * FROM %s ORDER BY queued_at, id', self::JOBS));
    }

    /**
     * @return array<string, mixed>
     */
    private function onlyJob(): array
    {
        $jobs = $this->jobs();
        self::assertCount(1, $jobs);

        return $jobs[0];
    }

    /**
     * @param array<string, mixed> $job
     */
    private static function jobId(array $job): JobId
    {
        self::assertIsString($job['id']);

        return JobId::fromString($job['id']);
    }

    /**
     * @param array<string, mixed> $job
     */
    private static function attempts(array $job): int
    {
        self::assertIsNumeric($job['attempts']);

        return (int) $job['attempts'];
    }

    private function waiting(string $queue): int
    {
        $count = $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE queue_name = ?', self::MESSAGES), [$queue]);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function rowsIn(string $table): int
    {
        $count = $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
        self::assertIsNumeric($count);

        return (int) $count;
    }

    /**
     * A worker, in this process: the same listeners and middleware as in
     * production.
     */
    private function consume(int $messages): void
    {
        $this->console([
            'command' => 'messenger:consume',
            'receivers' => ['async'],
            '--limit' => $messages,
            '--time-limit' => 10,
            '--sleep' => 0.05,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function console(array $input): void
    {
        self::assertNotNull(self::$kernel);
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        self::assertSame(0, $tester->run($input), $tester->getDisplay());
    }
}
