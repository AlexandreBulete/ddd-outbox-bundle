<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Tests\Integration\App;

use AlexandreBulete\DddFoundation\Application\Command\CommandInterface;

/**
 * An action whose effect is asynchronous: it sends NotifyCustomer, then
 * possibly fails — after the message was sent.
 *
 * @implements CommandInterface<void>
 */
final readonly class PlaceOrder implements CommandInterface
{
    public function __construct(
        public string $notification = 'ok',
        public bool $thenFail = false,
    ) {}
}
