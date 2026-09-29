<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Domain\ValueObject;

/**
 * Where an asynchronous message stands (ADR 0010):
 * queued → running → succeeded | retrying (→ running…) | failed.
 */
enum JobStatus: string
{
    /** Committed with the action that sent it, waiting for a worker. */
    case Queued = 'queued';
    /** A worker is handling it. */
    case Running = 'running';
    /** It failed, another attempt is scheduled. */
    case Retrying = 'retrying';
    case Succeeded = 'succeeded';
    /** Out of attempts: parked in the failure transport until retried. */
    case Failed = 'failed';
}
