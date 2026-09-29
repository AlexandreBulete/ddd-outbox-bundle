<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Tests\Unit\Domain;

use AlexandreBulete\DddOutboxBundle\Domain\Model\Job;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobId;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JobTest extends TestCase
{
    #[Test]
    public function a_job_starts_queued_and_is_not_retried_by_hand(): void
    {
        $job = Job::queue(JobId::generate(), 'App\\NotifyCustomer', 'async', 'user', 'u-1', 'Pauline', 'c-1', null, new \DateTimeImmutable());

        self::assertSame(JobStatus::Queued, $job->status);
        self::assertSame(0, $job->attempts);
        self::assertFalse($job->canBeRetried());
    }

    #[Test]
    public function a_job_belongs_to_a_chain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Job::queue(JobId::generate(), 'App\\NotifyCustomer', 'async', 'system', null, 'system', '', null, new \DateTimeImmutable());
    }
}
