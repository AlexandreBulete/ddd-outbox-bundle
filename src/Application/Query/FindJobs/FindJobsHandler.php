<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Application\Query\FindJobs;

use AlexandreBulete\DddFoundation\Application\Handler\QueryCollectionHandler;
use AlexandreBulete\DddFoundation\Application\Query\AsQueryHandler;
use AlexandreBulete\DddFoundation\Domain\Repository\RepositoryInterface;
use AlexandreBulete\DddOutboxBundle\Domain\Model\Job;
use AlexandreBulete\DddOutboxBundle\Domain\Repository\JobRepositoryInterface;

/**
 * @extends QueryCollectionHandler<Job>
 */
#[AsQueryHandler]
final readonly class FindJobsHandler extends QueryCollectionHandler
{
    public function __construct(JobRepositoryInterface $jobs)
    {
        parent::__construct($jobs);
    }

    /**
     * @return RepositoryInterface<Job>
     */
    public function __invoke(FindJobsQuery $query): RepositoryInterface
    {
        return $this->build($query);
    }
}
