<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Doctrine\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

/**
 * The follow-up of asynchronous messages (ADR 0010). No outbox table: the
 * Messenger Doctrine transport holds the messages; this one tells how each of
 * them is doing.
 *
 * A service (it needs `outbox.table`), written with the Schema API and DBAL
 * built-in types only: a migration is a snapshot.
 */
final class Version20261002140000 extends AbstractMigration
{
    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        private readonly string $table,
    ) {
        parent::__construct($connection, $logger);
    }

    public function getDescription(): string
    {
        return 'Outbox: the follow-up of asynchronous messages.';
    }

    public function up(Schema $schema): void
    {
        $job = $schema->createTable($this->table);
        $job->addColumn('id', Types::GUID);
        $job->addColumn('message', Types::STRING, ['length' => 255]);
        $job->addColumn('transport', Types::STRING, ['length' => 190]);
        $job->addColumn('status', Types::STRING, ['length' => 16]);
        $job->addColumn('attempts', Types::INTEGER);
        $job->addColumn('last_error', Types::TEXT, ['notnull' => false]);
        $job->addColumn('actor_kind', Types::STRING, ['length' => 10]);
        $job->addColumn('actor_id', Types::STRING, ['length' => 64, 'notnull' => false]);
        $job->addColumn('actor_label', Types::STRING, ['length' => 255]);
        $job->addColumn('correlation_id', Types::STRING, ['length' => 64]);
        $job->addColumn('causation_id', Types::STRING, ['length' => 64, 'notnull' => false]);
        $job->addColumn('queued_at', Types::DATETIME_IMMUTABLE);
        $job->addColumn('started_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $job->addColumn('finished_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $job->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $job->addIndex(['status']);
        $job->addIndex(['queued_at']);
        $job->addIndex(['correlation_id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable($this->table);
    }
}
