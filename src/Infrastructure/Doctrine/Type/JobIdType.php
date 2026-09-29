<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Doctrine\Type;

use AlexandreBulete\DddDoctrineBridge\Type\GuidType;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobId;

final class JobIdType extends GuidType
{
    public const NAME = 'outbox_job_id';

    protected string $name = self::NAME;
    protected string $voClass = JobId::class;
}
