<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Application\Command\RetryJob;

use AlexandreBulete\DddFoundation\Application\Activity\ActivityDescription;
use AlexandreBulete\DddFoundation\Application\Activity\JournaledInterface;
use AlexandreBulete\DddFoundation\Application\Command\CommandInterface;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobId;

/**
 * Relaunches a failed job (ADR 0010): a use case like any other — a
 * permission to hold, a line in the journal saying who relaunched what.
 *
 * @implements CommandInterface<void>
 */
final readonly class RetryJobCommand implements CommandInterface, JournaledInterface
{
    public function __construct(
        public JobId $id,
    ) {}

    public function describeActivity(): ActivityDescription
    {
        return new ActivityDescription(
            subjectType: 'job',
            subjectId: (string) $this->id,
            summary: 'outbox.activity.job_retried',
        );
    }
}
