# TASK-007 — Notification publisher + queued job

| Field | Value |
| :--- | :--- |
| **Status** | `planned` |
| **Priority** | P1 |
| **Value driver** | V-3 (auditable deletion), V-6 (integration surface) |
| **Depends on** | TASK-006 |
| **Created** | 2026-09-10 |

## Goal

Every `DeletionEvent` results in exactly one durable message on RabbitMQ,
describing the deleted document, the cause, and the mailbox that should be told —
so that a consumer elsewhere can send the email.

## Ontology Touchpoints

**Concepts:** `tds:NotificationMessage`, `tds:MessageQueue`, `tds:NotificationRecipient`
**Invariants:** **I-3** (one message per event), I-4 (publish after commit),
I-5 (message id = event id), I-9 (address from env), I-10 (survive a broker outage)
**New concepts:** none

## Acceptance Criteria

- [ ] AC-1 — `App\Domain\Notification\NotificationPublisher` interface is defined
      in the domain; `App\Infrastructure\Rabbit\RabbitPublisher` implements it
      (ADR-004). Domain code never references an AMQP class.
- [ ] AC-2 — `DocumentDeletedMessage` DTO serialises to JSON containing:
      `schema_version`, `event_id`, `occurred_at`, `trigger` (`manual_deletion` |
      `retention_expiry`), `recipient`, `subject`, and a `document` object with
      uuid, original name, size, mime type, uploaded at, expires at (T-13).
- [ ] AC-3 — `recipient` is read from `config('notifications.deletion_email')`,
      backed by `DELETION_NOTIFY_EMAIL`. The address appears nowhere in the
      repository (I-9, S-6).
- [ ] AC-4 — The AMQP message id equals the `DeletionEvent` uuid (I-5, T-14).
- [ ] AC-5 — Publication happens in a queued job dispatched **after commit**
      (ADR-003, I-4). A rolled-back deletion publishes nothing (T-10).
- [ ] AC-6 — The exchange and queue are declared **durable**, bound by
      `RABBITMQ_ROUTING_KEY`, and messages are marked persistent.
- [ ] AC-7 — A broker failure fails the job; it retries with backoff and succeeds
      once the broker returns, without creating a second `DeletionEvent`
      (I-10, T-11).
- [ ] AC-8 — Tests bind a fake `NotificationPublisher` and assert message content.
      At least one integration test publishes against a real broker and reads the
      message back — a fake-only suite cannot catch a misconfigured exchange.
- [ ] AC-9 — `subject` is precomputed so a consumer needs no domain knowledge.
- [ ] AC-10 — Publishing is triggered by the hook `TASK-006` AC-7 exposes, for
      **both** triggers, with no branch on which one it is.

## Out of Scope

- Consuming messages or sending email over SMTP — an explicit non-goal
  (`VALUE.md` §7). The brief scopes this project to publishing.
- A dead-letter queue and its alerting. Worth having in production; not required
  here, and it would need its own ontology concept.

## Notes

AC-10 matters more than it looks. If publication branches on the trigger, the two
paths can drift and one can lose its notification — the exact failure the brief
calls out. The publisher should not be able to tell manual from automatic except
as a field in the payload.

`schema_version` is in the payload from day one. It costs one line now, and
without it the first change to the message shape breaks every consumer silently.
