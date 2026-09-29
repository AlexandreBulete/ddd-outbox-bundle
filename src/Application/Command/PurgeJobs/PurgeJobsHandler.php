<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Application\Command\PurgeJobs;

use AlexandreBulete\DddFoundation\Application\Command\AsCommandHandler;
use AlexandreBulete\DddOutboxBundle\Domain\Repository\JobRepositoryInterface;

#[AsCommandHandler]
final readonly class PurgeJobsHandler
{
    public function __construct(
        private JobRepositoryInterface $jobs,
    ) {}

    /**
     * @return int<0, max>
     */
    public function __invoke(PurgeJobsCommand $command): int
    {
        return $this->jobs->purgeSucceededBefore($command->before);
    }
}
