<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Domain\ValueObject;

use AlexandreBulete\DddFoundation\Domain\ValueObject\IdentifierVO;

/**
 * A job is identified by the message it tracks: the `messageId` of its trace
 * (ddd-symfony-bundle), which survives retries and the failure transport.
 */
final readonly class JobId extends IdentifierVO
{
}
