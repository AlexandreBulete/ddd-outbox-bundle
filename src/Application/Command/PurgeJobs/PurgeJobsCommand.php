<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Application\Command\PurgeJobs;

use AlexandreBulete\DddFoundation\Application\Activity\ActivityDescription;
use AlexandreBulete\DddFoundation\Application\Activity\JournaledInterface;
use AlexandreBulete\DddFoundation\Application\Command\CommandInterface;

/**
 * @implements CommandInterface<int<0, max>>
 */
final readonly class PurgeJobsCommand implements CommandInterface, JournaledInterface
{
    public function __construct(
        public \DateTimeImmutable $before,
    ) {}

    public function describeActivity(): ActivityDescription
    {
        return new ActivityDescription(
            summary: 'outbox.activity.jobs_purged',
            summaryParams: ['before' => $this->before->format('Y-m-d')],
            details: ['before' => $this->before->format(\DATE_ATOM)],
        );
    }
}
