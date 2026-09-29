<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Domain\Repository;

use AlexandreBulete\DddFoundation\Domain\Repository\RepositoryInterface;
use AlexandreBulete\DddOutboxBundle\Domain\Model\Job;

/**
 * @extends RepositoryInterface<Job>
 */
interface JobRepositoryInterface extends RepositoryInterface
{
    /**
     * Removes the jobs that succeeded before that date. Failed ones stay:
     * they still wait for someone.
     *
     * @return int<0, max> how many were removed
     */
    public function purgeSucceededBefore(\DateTimeImmutable $before): int;
}
