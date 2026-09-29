<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Application\Command\RetryJob;

use AlexandreBulete\DddFoundation\Application\Command\AsCommandHandler;
use AlexandreBulete\DddFoundation\Domain\Exception\EntityNotFoundException;
use AlexandreBulete\DddOutboxBundle\Domain\Model\Job;
use AlexandreBulete\DddOutboxBundle\Domain\Repository\JobRepositoryInterface;
use AlexandreBulete\DddOutboxBundle\Domain\Service\JobRetrierInterface;

#[AsCommandHandler]
final readonly class RetryJobHandler
{
    public function __construct(
        private JobRepositoryInterface $jobs,
        private JobRetrierInterface $retrier,
    ) {}

    public function __invoke(RetryJobCommand $command): void
    {
        $job = $this->jobs->findById($command->id)
            ?? throw new EntityNotFoundException(Job::class, $command->id);

        if (!$job->canBeRetried()) {
            throw new \DomainException(sprintf('Only a failed job is retried by hand; this one is %s.', $job->status->value));
        }

        $this->retrier->retry($job->id);
    }
}
