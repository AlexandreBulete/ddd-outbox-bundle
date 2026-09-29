<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Sylius\Resource;

use AlexandreBulete\DddOutboxBundle\Domain\Model\Job;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Sylius\Grid\JobGrid;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\Uid\AbstractUid;

/**
 * Read-only by construction: a job is followed, not edited. Its one action,
 * the retry, is a use case of its own (RetryJobAction).
 */
#[AsResource(
    alias: 'outbox.job',
    section: 'admin',
    templatesDir: '@SyliusAdminUi/crud',
    routePrefix: '/admin',
    driver: false,
    operations: [
        new Index(grid: JobGrid::class),
    ],
)]
final class JobResource implements ResourceInterface
{
    public function __construct(
        public ?AbstractUid $id = null,
        public ?string $message = null,
        public ?string $transport = null,
        public ?string $status = null,
        public int $attempts = 0,
        public ?string $lastError = null,
        public ?string $actor = null,
        public ?string $correlationId = null,
        public ?\DateTimeImmutable $queuedAt = null,
        public ?\DateTimeImmutable $finishedAt = null,
        public bool $retriable = false,
    ) {}

    public function getId(): ?AbstractUid
    {
        return $this->id;
    }

    public static function fromModel(Job $job): self
    {
        $separator = strrpos($job->message, '\\');

        return new self(
            id: $job->id->value(),
            // The FQCN is unreadable in a cell; the filter still searches it.
            message: $separator === false ? $job->message : substr($job->message, $separator + 1),
            transport: $job->transport,
            status: $job->status->value,
            attempts: $job->attempts,
            lastError: $job->lastError,
            actor: $job->actorLabel . ($job->actorKind === 'user' ? '' : ' (' . $job->actorKind . ')'),
            correlationId: $job->correlationId,
            queuedAt: $job->queuedAt,
            finishedAt: $job->finishedAt,
            retriable: $job->canBeRetried(),
        );
    }
}
