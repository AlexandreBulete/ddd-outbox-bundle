<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Application\Query\FindJobs;

use AlexandreBulete\DddFoundation\Application\Query\QueryInterface;
use AlexandreBulete\DddOutboxBundle\Domain\Repository\JobRepositoryInterface;

/**
 * @implements QueryInterface<JobRepositoryInterface>
 */
final readonly class FindJobsQuery implements QueryInterface
{
    /**
     * @param array<string, mixed>  $criteria
     * @param array<string, string> $withSorting
     */
    public function __construct(
        public ?int $page = null,
        public ?int $itemsPerPage = null,
        public array $criteria = [],
        public array $withSorting = [],
    ) {}
}
