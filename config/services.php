<?php

declare(strict_types=1);

use AlexandreBulete\DddOutboxBundle\Application\Command\PurgeJobs\PurgeJobsHandler;
use AlexandreBulete\DddOutboxBundle\Application\Command\RetryJob\RetryJobHandler;
use AlexandreBulete\DddOutboxBundle\Application\Query\FindJobs\FindJobsHandler;
use AlexandreBulete\DddOutboxBundle\Domain\Repository\JobRepositoryInterface;
use AlexandreBulete\DddOutboxBundle\Domain\Service\JobRetrierInterface;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Doctrine\DoctrineJobRepository;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Doctrine\Listener\TableNameListener;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Messenger\FailureTransportRetrier;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Messenger\JobTracker;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Messenger\JobTrackingSubscriber;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Messenger\TransactionalSendGuard;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Symfony\Command\PurgeJobsConsoleCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // ── Read side and use cases ─────────────────────────────────────────────
    $services->set(DoctrineJobRepository::class);
    $services->alias(JobRepositoryInterface::class, DoctrineJobRepository::class);
    $services->set(FindJobsHandler::class);
    $services->set(RetryJobHandler::class);
    $services->set(PurgeJobsHandler::class);

    $services->set(TableNameListener::class)
        ->args([param('outbox.table')])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    // ── Follow-up: DBAL, on the application's connection (see JobTracker) ───
    $services->set(JobTracker::class)
        ->args([
            service('doctrine.dbal.default_connection'),
            service('clock'),
            param('outbox.table'),
        ]);
    $services->set(JobTrackingSubscriber::class);

    $services->set(FailureTransportRetrier::class)
        ->args([
            service('messenger.receiver_locator'),
            service(JobTracker::class),
            param('outbox.failure_transport'),
        ]);
    $services->alias(JobRetrierInterface::class, FailureTransportRetrier::class);

    // ── Guard: no asynchronous message sent after the commit ───────────────
    foreach (['command.bus', 'query.bus'] as $bus) {
        $services->set('ddd_outbox.transactional_send_guard.' . $bus, TransactionalSendGuard::class)
            ->decorate($bus)
            ->args([service('.inner'), service('messenger.senders_locator')]);
    }

    $services->set(PurgeJobsConsoleCommand::class)
        ->arg('$retentionDays', param('outbox.retention_days'));
};
