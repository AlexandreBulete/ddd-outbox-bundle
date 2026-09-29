<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Messenger;

use AlexandreBulete\DddOutboxBundle\Domain\Service\JobRetrierInterface;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobId;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\TraceStamp;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * Moves a failed message from the failure transport back to the transport it
 * failed on, with a fresh set of attempts (ADR 0010).
 *
 * Runs in the transaction of RetryJobCommand: on the Doctrine transport,
 * sending back and removing from the failure transport commit together.
 *
 * The message keeps its trace and its actor: it is still the same job, run on
 * behalf of whoever asked for it — authorization is checked again when it is
 * handled. Who relaunched it is in the journal, with the command.
 *
 * The failure transport is searched by trace: the id of a message in it is
 * not known outside the transport. Fine for what a failure transport should
 * hold — a handful of messages — not for thousands.
 */
final readonly class FailureTransportRetrier implements JobRetrierInterface
{
    public function __construct(
        private ContainerInterface $transports,
        private JobTracker $tracker,
        private string $failureTransport,
    ) {}

    public function retry(JobId $id): void
    {
        $failed = $this->transports->get($this->failureTransport);
        if (!$failed instanceof ListableReceiverInterface) {
            throw new \LogicException(sprintf('The failure transport "%s" cannot be listed.', $this->failureTransport));
        }

        foreach ($failed->all() as $envelope) {
            $trace = $envelope->last(TraceStamp::class);
            if ($trace === null || !JobId::fromString($trace->messageId)->equals($id)) {
                continue;
            }

            $origin = $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName()
                ?? throw new \LogicException('A message in the failure transport without its original transport.');
            $transport = $this->transports->get($origin);
            if (!$transport instanceof SenderInterface) {
                throw new \LogicException(sprintf('The transport "%s" cannot send.', $origin));
            }

            $transport->send($envelope
                ->withoutStampsOfType(NonSendableStampInterface::class)
                ->withoutAll(TransportMessageIdStamp::class)
                ->withoutAll(SentToFailureTransportStamp::class)
                ->withoutAll(RedeliveryStamp::class)
                ->withoutAll(ErrorDetailsStamp::class)
                ->withoutAll(DelayStamp::class));
            $failed->reject($envelope);
            $this->tracker->requeued($id);

            return;
        }

        throw new \DomainException('This job is no longer in the failure transport: it was retried or removed already.');
    }
}
