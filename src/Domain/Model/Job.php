<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Domain\Model;

use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobId;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobStatus;

/**
 * The follow-up of one asynchronous message (ADR 0010): what it is, who is
 * behind it, in which chain, and how far it got.
 *
 * Its row is written by the message's lifecycle — queued in the transaction
 * that sends it, then by the worker — not by use cases: this model is what
 * the screen and the retry read.
 */
final class Job
{
    private function __construct(
        private(set) JobId $id,
        private(set) string $message,
        private(set) string $transport,
        private(set) JobStatus $status,
        private(set) int $attempts,
        private(set) ?string $lastError,
        private(set) string $actorKind,
        private(set) ?string $actorId,
        private(set) string $actorLabel,
        private(set) string $correlationId,
        private(set) ?string $causationId,
        private(set) \DateTimeImmutable $queuedAt,
        private(set) ?\DateTimeImmutable $startedAt,
        private(set) ?\DateTimeImmutable $finishedAt,
    ) {}

    public static function queue(
        JobId $id,
        string $message,
        string $transport,
        string $actorKind,
        ?string $actorId,
        string $actorLabel,
        string $correlationId,
        ?string $causationId,
        \DateTimeImmutable $queuedAt,
    ): self {
        if ($message === '' || $transport === '' || $actorLabel === '' || $correlationId === '') {
            throw new \InvalidArgumentException('A job needs a message, a transport, an actor and a chain.');
        }

        return new self(
            $id, $message, $transport, JobStatus::Queued, 0, null,
            $actorKind, $actorId, $actorLabel, $correlationId, $causationId,
            $queuedAt, null, null,
        );
    }

    /**
     * Only a job out of attempts is retried by hand: one still queued,
     * running or retrying is already on its way.
     */
    public function canBeRetried(): bool
    {
        return $this->status === JobStatus::Failed;
    }
}
