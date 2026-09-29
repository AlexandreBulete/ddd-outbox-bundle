<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Messenger;

use AlexandreBulete\DddOutboxBundle\Domain\Model\Job;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobId;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;

/**
 * Writes the follow-up of asynchronous messages through DBAL (ADR 0010).
 *
 * `queue()` runs on the application's connection while the message is being
 * sent — inside the transaction of the action that sends it: the job exists if
 * and only if the message does. The other transitions come from the worker,
 * between transactions.
 *
 * The columns are the contract with Job.orm.xml.
 */
final readonly class JobTracker
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private string $table,
    ) {}

    public function queue(Job $job): void
    {
        $this->connection->insert($this->table, [
            'id' => $job->id->toRfc4122(),
            'message' => $job->message,
            'transport' => $job->transport,
            'status' => $job->status->value,
            'attempts' => $job->attempts,
            'last_error' => $job->lastError,
            'actor_kind' => $job->actorKind,
            'actor_id' => $job->actorId,
            'actor_label' => $job->actorLabel,
            'correlation_id' => $job->correlationId,
            'causation_id' => $job->causationId,
            'queued_at' => $job->queuedAt,
        ], [
            'attempts' => Types::INTEGER,
            'queued_at' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    public function running(JobId $id): void
    {
        $this->connection->executeStatement(
            "UPDATE {$this->table} SET status = ?, attempts = attempts + 1, started_at = ?, finished_at = NULL WHERE id = ?",
            [JobStatus::Running->value, $this->clock->now(), $id->toRfc4122()],
            [Types::STRING, Types::DATETIME_IMMUTABLE, Types::GUID],
        );
    }

    public function succeeded(JobId $id): void
    {
        $this->finish($id, JobStatus::Succeeded, null);
    }

    public function failed(JobId $id, string $error, bool $willRetry): void
    {
        $willRetry
            ? $this->update($id, JobStatus::Retrying, $error, null)
            : $this->finish($id, JobStatus::Failed, $error);
    }

    /**
     * Back on its transport after a retry by hand; the attempts made so far
     * and the last error stay, as history.
     */
    public function requeued(JobId $id): void
    {
        $this->connection->executeStatement(
            "UPDATE {$this->table} SET status = ?, finished_at = NULL WHERE id = ?",
            [JobStatus::Queued->value, $id->toRfc4122()],
        );
    }

    private function finish(JobId $id, JobStatus $status, ?string $error): void
    {
        $this->update($id, $status, $error, $this->clock->now());
    }

    private function update(JobId $id, JobStatus $status, ?string $error, ?\DateTimeImmutable $finishedAt): void
    {
        $this->connection->executeStatement(
            "UPDATE {$this->table} SET status = ?, last_error = COALESCE(?, last_error), finished_at = ? WHERE id = ?",
            [$status->value, $error, $finishedAt, $id->toRfc4122()],
            [Types::STRING, Types::TEXT, Types::DATETIME_IMMUTABLE, Types::GUID],
        );
    }
}
