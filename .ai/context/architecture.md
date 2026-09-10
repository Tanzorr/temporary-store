# Architecture

How the ontology maps onto code. The ontology says what exists; this file says
where it lives.

## Layering

```
HTTP / CLI                Controllers, Form Requests, Console Commands, Blade
   │                      Thin. Translate input, call a service, render output.
   ▼
Application services      UploadDocument, DeleteDocument, SweepExpiredDocuments
   │                      All domain rules. Own the transaction boundary.
   ▼
Domain                    Document, DeletionEvent, RetentionPolicy, UploadPolicy
   │                      Entities + value objects. No framework knowledge.
   ▼
Infrastructure            Eloquent models, Storage disks, RabbitMQ publisher
                          Adapters behind interfaces owned by the layer above.
```

Rule: **dependencies point downward only.** A domain object never imports a
controller, a request, or a facade.

## Concept → Code Map

Every ontology class must be findable in the codebase. If a concept has no home,
either the design is incomplete or the concept is not real.

| Ontology concept | Lives as |
| :--- | :--- |
| `Document` | `App\Models\Document` (Eloquent) + `documents` table |
| `StoredObject` | Value object `App\Domain\Storage\StoredObject` (disk + relative path), persisted as columns on `documents` |
| `UploadSession` | `App\Http\Requests\UploadDocumentRequest` + `App\Services\UploadDocument`. Not persisted — it exists for the duration of one request |
| `UploadPolicy` | `config/uploads.php` → `App\Domain\Upload\UploadPolicy` |
| `RetentionPolicy` | `config/retention.php` → `App\Domain\Retention\RetentionPolicy` |
| `RetentionSweep` | `App\Console\Commands\SweepExpiredDocuments` + `App\Services\SweepExpiredDocuments` |
| `DeletionEvent` | `App\Models\DeletionEvent` + `deletion_events` table |
| `DeletionTrigger` | PHP enum `App\Domain\Deletion\DeletionTrigger` (`MANUAL_DELETION`, `RETENTION_EXPIRY`) |
| `NotificationMessage` | `App\Domain\Notification\DocumentDeletedMessage` (DTO → JSON) |
| `MessageQueue` | `App\Infrastructure\Rabbit\RabbitPublisher` implementing `App\Domain\Notification\NotificationPublisher` |
| `NotificationRecipient` | `config/notifications.php` reading `DELETION_NOTIFY_EMAIL` |

## The Deletion Path

The single most important structure in the system. Invariant **I-3** says every
deletion emits exactly one notification. That is enforced structurally:

```
DocumentController@destroy ──┐
                             ├──► DeleteDocument::handle(Document, DeletionTrigger)
SweepExpiredDocuments ───────┘             │
                                           │  DB::transaction:
                                           │    1. create DeletionEvent
                                           │    2. purge StoredObject
                                           │    3. mark Document deleted
                                           │  after commit:
                                           └──► dispatch PublishDeletionNotification
                                                        │
                                                        └──► RabbitPublisher
```

Consequences, and the reason it is shaped this way:

- **`DeleteDocument` is the only place a Document may be deleted.** Not the
  controller, not the command, not an Eloquent observer. Two entry points, one
  implementation — so "notify on manual deletion too" cannot be forgotten in one
  of them.
- The trigger is a **required argument**. There is no default, so a new call site
  must consciously state its cause.
- Publication is dispatched **after commit** (`DB::afterCommit` / a queued job),
  never inside the transaction. Satisfies **I-4**: no notification for a deletion
  that rolled back.
- The job is queued and retried; the `DeletionEvent` id is the message id, making
  a retried publish de-duplicable downstream (**I-5**).
- Purge failure fails the transaction. A Document is never marked deleted while
  its bytes survive.

## Architecture Decision Records

Short, dated, and amended in place when superseded.

### ADR-001 — `DeletionEvent` is a table, not a `deleted_at` column
**2026-09-10 · Accepted**
A soft-delete column records *that* something was deleted but not *why*, and it
gives the two deletion paths no shared point to hang the notification on.
Modelling the event as a row makes the trigger explicit, gives the notification a
stable id, and makes S-2 (`count(events) == count(messages)`) a query rather than
a guess. Cost: one extra table and a join for history views. Accepted.

### ADR-002 — `expiresAt` is materialised at upload
**2026-09-10 · Accepted**
Storing the deadline lets the sweep select with `WHERE expires_at <= NOW()` on an
index instead of loading every row and computing in PHP. Cost: changing
`RETENTION_TTL_HOURS` does not retroactively move existing deadlines. That is
acceptable and arguably correct — a document's promise is fixed when it is made.

### ADR-003 — Notification is published from a queued job, not inline
**2026-09-10 · Accepted**
An inline publish couples the user's delete request to broker availability: a
RabbitMQ outage would make deletion fail or, worse, silently skip the
notification. A queued job with retries survives an outage and satisfies I-10.
Cost: the notification is eventually consistent, by seconds. Fine — nothing in
`VALUE.md` requires synchronous delivery.

### ADR-004 — Publisher hidden behind a domain interface
**2026-09-10 · Accepted**
`NotificationPublisher` is defined in the domain; `RabbitPublisher` implements it
in infrastructure. Tests bind a fake and assert on messages without a broker.
Cost: one interface. Buys testability of I-3 and I-5.

### ADR-005 — Server-rendered Blade + jQuery, no build step
**2026-09-10 · Accepted**
The brief specifies Bootstrap + jQuery. A Vite/React setup would add tooling the
reviewer did not ask for and lengthens `README` setup. Async upload uses
`XMLHttpRequest` with an `upload.onprogress` handler — enough for a progress bar.

### ADR-006 — Content-based MIME detection
**2026-09-10 · Accepted**
The client `Content-Type` header is attacker-controlled. Detection uses PHP's
fileinfo on the temporary upload, checked against the `UploadPolicy` whitelist
(**I-8**). Extension alone is never sufficient.
