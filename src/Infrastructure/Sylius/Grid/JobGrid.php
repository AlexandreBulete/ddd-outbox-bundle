<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Sylius\Grid;

use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobStatus;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Sylius\Resource\JobResource;
use Sylius\Bundle\GridBundle\Builder\Field\DateTimeField;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Builder\Field\TwigField;
use Sylius\Bundle\GridBundle\Builder\Filter\SelectFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

class JobGrid extends AbstractGrid implements ResourceAwareGridInterface
{
    /**
     * @param list<int> $limits
     */
    public function __construct(
        private readonly array $limits,
    ) {}

    public static function getName(): string
    {
        return self::class;
    }

    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
        $statuses = [];
        foreach (JobStatus::cases() as $status) {
            $statuses['outbox.status.' . $status->value] = $status->value;
        }

        $gridBuilder
            ->setProvider(JobGridProvider::class)
            ->setLimits($this->limits)
            ->orderBy('queuedAt', 'desc')
            ->addFilter(SelectFilter::create('status', $statuses)->setLabel('outbox.field.status'))
            ->addFilter(StringFilter::create('message', ['message'])->setLabel('outbox.field.message'))
            ->addFilter(StringFilter::create('correlationId', ['correlation_id'])->setLabel('outbox.field.chain'))
            ->addField(DateTimeField::create('queuedAt')->setLabel('outbox.field.queued_at')->setSortable(true))
            ->addField(StringField::create('message')->setLabel('outbox.field.message'))
            ->addField(TwigField::create('status', '@DddOutbox/admin/grid/status.html.twig')->setLabel('outbox.field.status'))
            ->addField(StringField::create('attempts')->setLabel('outbox.field.attempts'))
            ->addField(StringField::create('actor')->setLabel('outbox.field.actor'))
            ->addField(StringField::create('lastError')->setLabel('outbox.field.last_error'))
            ->addField(DateTimeField::create('finishedAt')->setLabel('outbox.field.finished_at'))
            ->addField(StringField::create('correlationId')->setLabel('outbox.field.chain'))
            ->addField(TwigField::create('retriable', '@DddOutbox/admin/grid/retry.html.twig')->setPath('.')->setLabel('outbox.field.action'))
        ;
    }

    public function getResourceClass(): string
    {
        return JobResource::class;
    }
}
