<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Tests\Integration\App;

/**
 * A reaction with an effect (ADR 0010): its own message, routed to `async`.
 *
 * - `ok`: done;
 * - `flaky`: fails until NotifyCustomerHandler::$recovered;
 * - `poison`: closes the EntityManager, then fails — what a database error does;
 * - `orm`: needs the EntityManager.
 */
final readonly class NotifyCustomer
{
    public function __construct(
        public string $behaviour = 'ok',
    ) {}
}
