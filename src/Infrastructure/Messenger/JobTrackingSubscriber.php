<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Messenger;

use AlexandreBulete\DddOutboxBundle\Domain\Model\Job;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobId;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\ActorStamp;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\TraceStamp;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

/**
 * Follows every asynchronous message through its lifecycle (ADR 0010).
 *
 * Only messages leaving for a real transport are followed — the `sync`
 * transport is a function call — and only traced ones: the trace gives the
 * job its id, its actor and its chain.
 *
 * Queuing fails loudly: it runs in the sender's transaction, and a message
 * nobody could follow must not leave. The worker-side transitions never do:
 * bookkeeping must not turn a handled message into a failed one.
 */
final readonly class JobTrackingSubscriber implements EventSubscriberInterface
{
    private const ERROR_LENGTH = 2000;

    public function __construct(
        private JobTracker $tracker,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => 'onSend',
            WorkerMessageReceivedEvent::class => 'onReceived',
            WorkerMessageHandledEvent::class => 'onHandled',
            // After Messenger's retry listener (priority 100), which decides
            // whether the message will be retried.
            WorkerMessageFailedEvent::class => ['onFailed', -10],
        ];
    }

    public function onSend(SendMessageToTransportsEvent $event): void
    {
        $envelope = $event->getEnvelope();
        // A retry or a move to the failure transport: the job already exists.
        if ($envelope->last(RedeliveryStamp::class) !== null || $envelope->last(SentToFailureTransportStamp::class) !== null) {
            return;
        }

        $transports = [];
        foreach ($event->getSenders() as $name => $sender) {
            if (!$sender instanceof SyncTransport) {
                $transports[] = $name;
            }
        }

        $trace = $envelope->last(TraceStamp::class);
        $actor = $envelope->last(ActorStamp::class)?->actor;
        if ($transports === [] || $trace === null || $actor === null) {
            return;
        }

        $this->tracker->queue(Job::queue(
            id: JobId::fromString($trace->messageId),
            message: $envelope->getMessage()::class,
            transport: implode(', ', $transports),
            actorKind: $actor->kind->value,
            actorId: $actor->id,
            actorLabel: $actor->label,
            correlationId: $trace->correlationId,
            causationId: $trace->causationId,
            queuedAt: $this->clock->now(),
        ));
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->safely($event->getEnvelope(), fn (JobId $id) => $this->tracker->running($id));
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->safely($event->getEnvelope(), fn (JobId $id) => $this->tracker->succeeded($id));
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $error = self::summarize($event->getThrowable());
        $this->safely($event->getEnvelope(), fn (JobId $id) => $this->tracker->failed($id, $error, $event->willRetry()));
    }

    /**
     * @param \Closure(JobId): void $transition
     */
    private function safely(Envelope $envelope, \Closure $transition): void
    {
        $trace = $envelope->last(TraceStamp::class);
        if ($trace === null) {
            return;
        }

        try {
            $transition(JobId::fromString($trace->messageId));
        } catch (\Throwable $e) {
            $this->logger->error('Could not follow up the job of {message}.', [
                'message' => $envelope->getMessage()::class,
                'exception' => $e,
            ]);
        }
    }

    /**
     * The handler's exception, not Messenger's wrapper around it; its short
     * class and message, bounded.
     */
    private static function summarize(\Throwable $error): string
    {
        if ($error instanceof HandlerFailedException) {
            $error = $error->getWrappedExceptions()[array_key_first($error->getWrappedExceptions())] ?? $error;
        }

        $class = strrchr($error::class, '\\');
        $summary = ($class === false ? $error::class : substr($class, 1)) . ': ' . $error->getMessage();

        return mb_substr($summary, 0, self::ERROR_LENGTH);
    }
}
