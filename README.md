# DDD Outbox Bundle

Asynchronous effects you can trust: **nothing lost, nothing phantom, nothing
twice** — and every one of them followed, explained and retried on its own.

**There is no outbox table in this bundle.** On the Messenger Doctrine
transport, a message sent while an action is handled is inserted in that
action's transaction: it leaves if and only if the action commits. The
transport *is* the outbox. What this bundle adds is what makes it safe to
rely on: a guard against the one way to break that property, a follow-up of
every message, a back-office screen and a retry.

If you come from a hand-rolled outbox (a table plus a relay every minute):
there is no relay, no minute of latency — PostgreSQL's LISTEN/NOTIFY wakes the
worker on insert — and one reaction is one message, retried on its own.

## Install

```bash
composer require alexandrebulete/ddd-outbox-bundle
bin/console doctrine:migrations:migrate
```

```yaml
# config/routes/outbox.yaml — the "Retry" action of the back office
outbox:
    resource: '@DddOutboxBundle/config/routes.php'
```

Requires `alexandrebulete/ddd-symfony-bundle` ≥ 1.5, whose tracing gives each
message its id, its actor and its chain.

The promise holds on these conditions — check them once:

```yaml
framework:
    messenger:
        failure_transport: failed
        transports:
            # Doctrine, on the application's own connection: that is the outbox.
            async: 'doctrine://default'
            failed: 'doctrine://default?queue_name=failed'
```

- **Doctrine transport, same connection** as the entities. Another transport
  (Redis, AMQP) cannot join the database transaction: the guarantee is gone.
- **A failure transport**: that is where failed jobs wait to be retried.
- On PostgreSQL, keep `use_notify` (on by default): it is what makes the
  latency milliseconds rather than a polling interval. A *delayed* message —
  an automatic retry — is not notified when it becomes due: the worker looks
  for them every `check_delayed_interval` (60 000 ms by default). Lower it on
  the transport if retries must be quicker.

## Writing an asynchronous effect

One reaction with an effect, one message — never a domain event fanned out to
N asynchronous subscribers: each effect then has its own retries, its own
failure, its own retry by hand.

```php
// Routed to `async`. Not a CommandInterface: those are routed to `sync`.
final readonly class SendMissionReport
{
    public function __construct(public string $missionId, public string $step) {}
}

// In the handler of the action — inside its transaction:
$this->commandBus->dispatch(new SendMissionReport($missionId, $step));
```

The handler must be **idempotent** (at-least-once delivery): derive a key from
what the message is about — (mission, step) — and do nothing if it is done.

**Never `DispatchAfterCurrentBusStamp`** for an asynchronous message: it would
leave after the commit, and be lost if the process stops in between. The
bundle refuses it with a `LogicException`.

## What is guaranteed

| | How |
|---|---|
| Sent if and only if the action commits | the Doctrine transport inserts in the action's transaction; `DispatchAfterCurrentBusStamp` refused |
| A failure does not freeze the queue | each message is retried on its own, then parked in the failure transport; a message that closes the EntityManager does not take the next ones down (the worker resets it between messages) |
| Every message followed | a job per message: `queued` → `running` → `succeeded` \| `retrying` → `failed` |

A job is written in the same transaction as its message: it exists if and
only if the message does. It carries the message, the transport, who is
behind it, its chain (`correlation_id`, `causation_id` — the activity journal
of ddd-activity-bundle shares them), its attempts and last error.

Only messages leaving for a real transport are followed; `sync` is a function
call.

## Back office

With Sylius Admin UI, "Background jobs" lists jobs with filters (status, job,
chain): what is stuck, started by whom, since when, why. A failed job has a
**Retry** button: `RetryJobCommand`, a use case like any other — a permission
(`outbox.retry_job`), a line in the journal. The message goes back on its
transport with a fresh set of attempts, still on behalf of whoever started it.

## Retention

```bash
bin/console outbox:purge    # succeeded jobs older than outbox.retention_days
```

Failed jobs are kept: they still wait for someone.

## Configuration

```yaml
outbox:
    table: outbox_job          # default
    failure_transport: failed  # default
    retention_days: 30         # default
    admin:
        enabled: true          # false = no Sylius screen (headless)
        grid_limits: [25, 50, 100]
```

## Development

```bash
composer install
composer qa    # phpstan (max + strict rules), deptrac, phpunit
```

The integration tests run a real worker on real Doctrine transports, on the
database given by `DDD_TEST_DATABASE_URL` (a SQLite file otherwise); CI runs
them on PostgreSQL, MySQL and SQLite.
