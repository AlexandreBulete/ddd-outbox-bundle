<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Doctrine\Listener;

use AlexandreBulete\DddOutboxBundle\Domain\Model\Job;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/**
 * Applies `outbox.table` to the mapping, whose table name is only the default.
 */
final readonly class TableNameListener
{
    public function __construct(
        private string $table,
    ) {}

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();

        if ($metadata->getName() === Job::class) {
            $metadata->setPrimaryTable(['name' => $this->table]);
        }
    }
}
