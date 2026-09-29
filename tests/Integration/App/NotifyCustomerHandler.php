<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Tests\Integration\App;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class NotifyCustomerHandler
{
    public static bool $recovered = false;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(NotifyCustomer $message): void
    {
        match ($message->behaviour) {
            'flaky' => self::$recovered ? null : throw new \RuntimeException('Mail server down.'),
            'poison' => $this->poison(),
            'orm' => $this->em->persist(new Thing('notified')),
            default => null,
        };
    }

    private function poison(): never
    {
        $this->em->close();

        throw new \RuntimeException('Database connection lost.');
    }
}
