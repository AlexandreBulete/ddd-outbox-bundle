<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

/**
 * Refuses the one way to lose an effect (ADR 0010): an asynchronous message
 * sent with DispatchAfterCurrentBusStamp leaves after the transaction commits,
 * so a crash in between loses it — a validated deployment that never runs.
 *
 * Sent without it, the message is inserted in the transaction of the action
 * that sends it: it leaves if and only if the action commits. That is the
 * whole outbox.
 */
final readonly class TransactionalSendGuard implements MessageBusInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private SendersLocatorInterface $senders,
    ) {}

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);

        if ($envelope->last(DispatchAfterCurrentBusStamp::class) !== null) {
            foreach ($this->senders->getSenders($envelope) as $name => $sender) {
                if (!$sender instanceof SyncTransport) {
                    throw new \LogicException(sprintf(
                        '%s is sent to "%s" with DispatchAfterCurrentBusStamp: it would leave after the commit, and be lost if the process stops in between. Dispatch it without the stamp — it then leaves with the transaction (ADR 0010).',
                        $envelope->getMessage()::class,
                        $name,
                    ));
                }
            }
        }

        return $this->bus->dispatch($envelope);
    }
}
