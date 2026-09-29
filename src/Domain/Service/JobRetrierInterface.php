<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Domain\Service;

use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobId;

/**
 * Domain port — puts a failed job's message back on the transport it came
 * from, with a fresh set of attempts.
 */
interface JobRetrierInterface
{
    /**
     * @throws \DomainException when the message is no longer waiting to be retried
     */
    public function retry(JobId $id): void;
}
