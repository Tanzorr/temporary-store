# TASK-007 — Notification publisher + queued job

| Field | Value |
| :--- | :--- |
| **Status** | `done` |
| **Priority** | P1 |
| **Value driver** | V-3 (auditable deletion), V-6 (integration surface) |
| **Depends on** | TASK-006 |
| **Created** | 2026-09-10 |

## Goal

Every `DeletionEvent` results in exactly one durable message on RabbitMQ,
describing the deleted document, the cause, and the mailbox that should be told —
so that a consumer elsewhere can send the email.

> **TASK-006 is `done`** (archived 2026-09-10), so this ticket is unblocked.
> `App\Services\DeleteDocument` already dispatches `App\Events\DocumentDeleted`
> from inside `DB::afterCommit`. **The after-commit half of AC-5 is therefore
> already satisfied upstream — do not re-implement it here.** This ticket adds
> the listener that consumes that event and everything downstream of it.

## Ontology Touchpoints

**Concepts:** `tds:NotificationMessage`, `tds:MessageQueue`, `tds:NotificationRecipient`
**Invariants:** **I-3** (one message per event), I-4 (publish after commit),
I-5 (message id = event id), I-9 (address from env), I-10 (survive a broker outage)
**New concepts:** none. `DocumentDeletedMessage` is `NotificationMessage` given a
class, and the listener is wiring — neither touches `ontology.md`. Say so in the
outcome note rather than leaving a reviewer to wonder whether it was forgotten.

## Shape to Build

Fixed here so naming and placement are not re-invented at implementation time.

| Path | Role |
| :--- | :--- |
| `composer.json` / `composer.lock` | `php-amqplib/php-amqplib` — **the only new dependency**; see *Preconditions* |
| `app/Domain/Notification/NotificationPublisher.php` | **new directory** — interface (ADR-004): `publish(DocumentDeletedMessage $message): void`. No AMQP class is named anywhere in `App\Domain` |
| `app/Domain/Notification/DocumentDeletedMessage.php` | DTO built from a `DeletionEvent`; exposes `messageId` and the payload of *Message Contract* |
| `app/Infrastructure/Rabbit/RabbitPublisher.php` | **new directory** — the only class that imports `PhpAmqpLib\*`. Declares exchange + queue durable, binds, publishes persistent |
| `app/Jobs/PublishDeletionNotification.php` | **new directory** — queued job: `$tries`, `backoff()`, calls the publisher and nothing else |
| `app/Listeners/DispatchDeletionNotification.php` | **new directory** — `handle(DocumentDeleted $event)` dispatches the job. Auto-discovered; see *Preconditions* |
| `app/Providers/AppServiceProvider.php` | bind `NotificationPublisher` → `RabbitPublisher` in `register()` |
| `phpunit.xml` | new `Integration` testsuite — it does not exist yet |
| `tests/Unit/Domain/Notification/DocumentDeletedMessageTest.php` | new — T-13, payload shape and `schema_version` |
| `tests/Feature/Notification/PublishDeletionNotificationTest.php` | new — T-6 (message half), T-7, T-10, T-14, AC-10 |
| `tests/Integration/RabbitPublisherTest.php` | new — AC-8, real broker round-trip, skipped when absent |
| `.ai/context/architecture.md` | ADR-014 (see *Notes*) |
| `CHANGELOG.md` | TASK-007 entry |

### Message Contract

The published JSON body, fixed so the test and the DTO cannot disagree:

```json
{
  "schema_version": 1,
  "event_id": "<deletion_events.uuid>",
  "occurred_at": "<ISO-8601, UTC>",
  "trigger": "manual_deletion | retention_expiry",
  "recipient": "<config('notifications.recipient_email')>",
  "subject": "Document deleted: <original_name>",
  "document": {
    "uuid": "…", "original_name": "…", "size_bytes": 0,
    "mime_type": "…", "uploaded_at": "…", "expires_at": "…"
  }
}
```

| AMQP property | Value | Why |
| :--- | :--- | :--- |
| `message_id` | `deletion_events.uuid` | I-5 / T-14 — a retried publish is de-duplicable |
| `delivery_mode` | `2` (persistent) | AC-6 — survives a broker restart |
| `content_type` | `application/json` | so a consumer needs no out-of-band knowledge |

`sweep_id` is **not** in the payload. It correlates a sweep's rows for an
operator reading logs; a downstream consumer has no use for it, and adding a
field is cheap now and permanent afterwards.

## Acceptance Criteria

- [x] AC-1 — `App\Domain\Notification\NotificationPublisher` interface is defined
      in the domain; `App\Infrastructure\Rabbit\RabbitPublisher` implements it
      (ADR-004). Domain code never references an AMQP class.
- [x] AC-2 — `DocumentDeletedMessage` DTO serialises to JSON containing:
      `schema_version`, `event_id`, `occurred_at`, `trigger` (`manual_deletion` |
      `retention_expiry`), `recipient`, `subject`, and a `document` object with
      uuid, original name, size, mime type, uploaded at, expires at (T-13).
- [x] AC-3 — `recipient` is read from `config('notifications.recipient_email')`,
      backed by `DELETION_NOTIFY_EMAIL`. The address appears nowhere in the
      repository (I-9, S-6). *(The key is `recipient_email`, not
      `deletion_email` as this AC originally said — the config file shipped in
      `TASK-001` and nothing else reads it; see* Preconditions*.)*
- [x] AC-4 — The AMQP message id equals the `DeletionEvent` uuid (I-5, T-14).
- [x] AC-5 — Publication happens in a queued job. The **after-commit** half
      (ADR-003, I-4) is already delivered by `TASK-006`'s `DB::afterCommit`
      dispatch of `DocumentDeleted` — this ticket must not add a second
      after-commit mechanism, only listen. A rolled-back deletion publishes
      nothing (T-10), and the test for it asserts through the real service, not
      by calling the listener directly.
- [x] AC-6 — The exchange and queue are declared **durable**, bound by
      `RABBITMQ_ROUTING_KEY`, and messages are marked persistent.
- [x] AC-7 — A broker failure fails the job; it retries with backoff and succeeds
      once the broker returns, without creating a second `DeletionEvent`
      (I-10, T-11).
- [x] AC-8 — Tests bind a fake `NotificationPublisher` and assert message content.
      At least one integration test publishes against a real broker and reads the
      message back — a fake-only suite cannot catch a misconfigured exchange. It
      lives in a **new `Integration` testsuite** in `phpunit.xml` and
      `markTestSkipped`s when the broker is unreachable, so `php artisan test`
      still passes on a machine with no RabbitMQ (`testing.md` → Layers).
- [x] AC-9 — `subject` is precomputed so a consumer needs no domain knowledge.
- [x] AC-10 — Publishing is triggered by the hook `TASK-006` AC-7 exposes, for
      **both** triggers, with no branch on which one it is. The test proves this
      by driving `DeleteDocument::handle()` twice — once per `DeletionTrigger` —
      and asserting two recorded messages differing only in `trigger`.
- [x] AC-11 — Every row of *Manual Verification* passes against
      `docker compose up`, and the result is recorded in the outcome note.

### Manual Verification

A fake publisher cannot catch a misdeclared exchange or a worker that never
picked the job up, so these are checked once against the running stack.

| Check | Expected |
| :--- | :--- |
| Delete a document in the UI, then `docker compose exec rabbitmq rabbitmqctl list_queues name durable messages` | `document.deletions` exists, `durable=true`, count up by exactly 1 |
| Read that message back | body matches *Message Contract*; `message_id` equals the `deletion_events.uuid` |
| `docker compose logs queue` | shows the job processed, no failures |
| Stop the broker, delete a document, start the broker | the deletion still succeeds; the job retries and the message arrives; still exactly **one** `DeletionEvent` (I-10, S-5) |
| `git grep` for the recipient address | no hits (I-9, S-6) |

## Preconditions in the Current Tree

Verified against `feature/document-lifecycle` on 2026-09-10 — facts that change
how the ACs are met, not restatements of them.

| Fact | Consequence |
| :--- | :--- |
| `php-amqplib/php-amqplib` is **not** in `composer.json` | `composer require` it first. `stack.md` already chose this library — the image installs `sockets` *because* it needs it — but the install is `ask`-mode in `.claude/settings.json` and must be raised, not slipped in (CLAUDE.md → hard rule 4) |
| `php -m` in the `app` container lists `sockets` **and** `mbstring` | php-amqplib's two hard requirements are already met; **no image rebuild** |
| `fsockopen('rabbitmq', 5672)` succeeds from the `app` container | the integration test is viable; use the same call as its skip check |
| `rabbitmqctl list_exchanges` shows only AMQP defaults — no `documents` exchange, no `document.deletions` queue | the publisher must declare and bind them itself; nothing pre-exists to lean on |
| `config/notifications.php` defines **`recipient_email`**, and `rg` finds no other reader | AC-3's `deletion_email` was wrong. Keep the shipped key, do not rename a config nobody reads just to match a sentence |
| `config/rabbitmq.php` has host, port, user, password, exchange, queue, routing_key — **no `vhost`** | php-amqplib defaults `vhost` to `/`, which is what the broker uses. Do not add a config key that is not in `stack.md`'s env table |
| `Application::configure()` calls `->withEvents()`, `EventServiceProvider::$shouldDiscoverEvents` is `true`, and discovery scans `app/Listeners` | a listener class there with a typed `handle(DocumentDeleted $event)` **self-registers** — no manual `Event::listen`. Confirm with `php artisan event:list`, because a mistyped hint fails silently |
| `App\Events\DocumentDeleted` carries `public readonly DeletionEvent $deletionEvent` and uses `SerializesModels` | the listener reads the event straight off it. `deletion_events` rows are never deleted, so re-fetch on unserialize is safe |
| `DeletionEvent` has a `document()` `BelongsTo`, and a deleted `Document` keeps its row as a tombstone | the payload's `document` object is available after deletion — no need to copy metadata onto the event row |
| `phpunit.xml` declares only `Unit` and `Feature` suites | AC-8 needs a third; `tests/Integration/` does not exist yet |
| `phpunit.xml` forces `QUEUE_CONNECTION=sync` | a dispatched job runs **inline** in tests. That is what satisfies `testing.md`'s "at least one test must run the job for real"; use `Queue::fake()` only where the assertion really is "was it dispatched" |
| Under `QUEUE_CONNECTION=sync` a failing job throws at the dispatch site instead of being retried by a worker | T-11/AC-7 cannot be proven by "dispatch and wait". Drive it with a fake publisher that throws on the first call and succeeds on the second, invoking the job twice as the worker would, and assert one `DeletionEvent` and one delivered message |
| `phpunit.xml` forces `DB_CONNECTION=sqlite` for every suite | `testing.md` describes Integration as "real MySQL + RabbitMQ". The half that carries the risk is the **broker**; leave the DB as sqlite and record that deviation in the outcome note rather than bolting on a second test database |
| `config/queue.php`'s `database` connection sets `'after_commit' => false` | harmless — the dispatch already happens inside `DB::afterCommit` (TASK-006). Worth stating in the outcome note, because it looks like an I-4 hole to a reviewer who has not read `DeleteDocument` |
| The `queue` container runs a long-lived `php artisan queue:work` | it caches the autoloader: after `composer require` and the new job class, **restart it** (`docker compose restart queue`) or it will never see them |
| Six services healthy under `docker compose ps` | verify with `docker compose exec app php artisan test` and `./vendor/bin/pint --test` |

## Out of Scope

- Consuming messages or sending email over SMTP — an explicit non-goal
  (`VALUE.md` §7). The brief scopes this project to publishing.
- A dead-letter queue and its alerting. Worth having in production; not required
  here, and it would need its own ontology concept.
- Publishing from the sweep. `TASK-008` calls the same `DeleteDocument`, so it
  inherits this path with no publisher change — that is the point of AC-10.
- A broker health-check command, publish metrics, or a `vhost` setting. None is
  named in `stack.md`'s env table.
- Changing `config/queue.php`'s connections or the `queue` container's command.

## Notes

AC-10 matters more than it looks. If publication branches on the trigger, the two
paths can drift and one can lose its notification — the exact failure the brief
calls out. The publisher should not be able to tell manual from automatic except
as a field in the payload.

`schema_version` is in the payload from day one. It costs one line now, and
without it the first change to the message shape breaks every consumer silently.

**ADR-014 — a plain listener dispatching a queued job, not a queued listener.**
Write it into [`architecture.md`](../context/architecture.md) as part of this
change. Laravel would let the listener itself implement `ShouldQueue`, which is
one class instead of two. Rejected: the queue would then hold a
`CallQueuedListener` wrapper, so `queue:failed` and the logs name the wrapper
rather than the work, and the retry policy would sit on a class whose job is
wiring. `architecture.md`'s deletion diagram already names
`PublishDeletionNotification` as the thing that runs after commit, and
`conventions.md`'s structure block already has `Jobs/` for queued work. Cost:
one extra class and one extra hop. Accepted — the hop is where I-10's retry
policy lives, and it keeps the listener a two-line translation.

**Two ambiguities were resolved during preparation** — recorded here so the
implementer does not re-litigate them, and a reviewer can disagree in one place:

1. **`recipient_email`, not `deletion_email`.** AC-3 named a config key that
   does not exist. The shipped key wins over the sentence.
2. **The integration test runs against sqlite + a real broker**, not real MySQL.
   See *Preconditions*.

**The trap in this ticket is a green suite that proves nothing.** With a fake
publisher bound and `QUEUE_CONNECTION=sync`, every test can pass while the
exchange name is wrong, the broker is unreachable, or the listener was never
registered because its type hint has a typo. AC-8's integration test and
`php artisan event:list` are the two checks that catch exactly that, and they
are the ones worth running first, not last.

---

## Outcome

**Completed:** 2026-09-10

**What was built:** `App\Domain\Notification\NotificationPublisher` (interface)
and `DocumentDeletedMessage` (DTO, built via `fromDeletionEvent()`, no branch on
trigger). `App\Infrastructure\Rabbit\RabbitPublisher` implements the interface —
the only class importing `PhpAmqpLib\*` — opening one connection per publish,
declaring the exchange/queue/binding durable, and publishing persistent with
`message_id` set to the `DeletionEvent` uuid. `App\Jobs\PublishDeletionNotification`
(`$tries = 5`, `backoff() = [10, 30, 60, 120]`) calls the publisher and nothing
else. `App\Listeners\DispatchDeletionNotification` is a plain, auto-discovered
listener (confirmed via `php artisan event:list`) that dispatches the job —
ADR-014 records why it isn't a queued listener. Bound in
`AppServiceProvider::register()`. New dependency: `php-amqplib/php-amqplib`
(raised and approved before `composer require`). New `Integration` testsuite in
`phpunit.xml`.

**Deviations from the plan:**
- A `Tests\Support\RecordingNotificationPublisher` fake was added (not in the
  ticket's Shape to Build table) — testing.md requires a fake that "fails like
  the real thing," and no such double existed yet. It records published
  messages and can be told to throw on its next N calls, used to drive AC-7/T-11.
- AC-7/T-11 (retry-without-duplication) is covered in
  `PublishDeletionNotificationTest.php` alongside T-6/T-7/T-10/T-14/AC-10, per
  the Preconditions guidance on driving it by hand — the Shape table's row for
  that file didn't enumerate it, but no other file was designated for it either.
- Manual verification was driven with `curl` directly against the real HTTP
  endpoints (`POST /documents`, `DELETE /documents/{uuid}`) rather than a
  headless browser — no browser automation tool was available in this session.
  Same code path the jQuery UI calls.
- The two ambiguities already flagged in this ticket (`recipient_email` over
  `deletion_email`; Integration suite against sqlite + a real broker, not real
  MySQL) were followed as written, not re-litigated.

**Invariants verified:**
- **I-3** — feature tests and a live run: one `DELETE` → queue count up by
  exactly one; `DeletionEvent::count()` matches.
- **I-4** — rollback test (purge failure) asserts the fake publisher recorded
  nothing; `DB::afterCommit` itself was TASK-006's, unchanged here.
- **I-5** — unit, feature, and integration tests assert `message_id` equals
  `deletion_events.uuid`; confirmed again by hand via `rabbitmqadmin get`.
- **I-9** — `rg` for the recipient address across the repo (excluding
  `vendor/`) finds only pre-existing, unrelated `@example.com` placeholders
  (`config/mail.php`, `DatabaseSeeder`) and this ticket's own test fixture —
  never the real key's value.
- **I-10** — live broker-outage test: `docker compose stop rabbitmq`, deleted a
  document (succeeded in 64ms, uncoupled from broker state), watched the `jobs`
  table directly and confirmed the backoff schedule fires at 10s, 30s, then 60s
  exactly as coded; `docker compose start rabbitmq`, the job then delivered the
  message; `DeletionEvent` count for that document stayed at 1 throughout.

**Manual Verification (AC-11):** all five rows run against `docker compose up`
— durable queue confirmed via `rabbitmqctl list_queues`, message body matched
the Message Contract exactly (`rabbitmqadmin get`), `docker compose logs queue`
showed `DONE` with no unexpected failures on the happy path, the broker-outage
row is detailed under I-10 above, and the `rg` recipient-address search is
under I-9. The `document.deletions` queue was purged of verification artifacts
afterward; the six services were healthy throughout (`docker compose ps`).

**Follow-ups raised:** none. `TASK-008` (retention sweep) is now unblocked.
