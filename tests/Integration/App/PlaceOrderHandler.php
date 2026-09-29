<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Tests\Integration\App;

use AlexandreBulete\DddFoundation\Application\Command\AsCommandHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommandHandler]
final readonly class PlaceOrderHandler
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {}

    public function __invoke(PlaceOrder $command): void
    {
        $this->commandBus->dispatch(new NotifyCustomer($command->notification));

        if ($command->thenFail) {
            throw new \DomainException('Out of stock.');
        }
    }
}
