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
| `RetentionSweep` | `App\Console\Commands\SweepExpiredDocuments` + `App\Services\SweepExpiredDocuments`. Not persisted: a run's identity is a generated `sweep_id` stamped on each `DeletionEvent` it raises; its counts go to the log |
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

### ADR-007 — The JSON API is the only async surface; no representation resolver
**2026-09-10 · Accepted**
Considered and rejected: actions returning plain arrays with a `RespondsWithView`
trait picking full view / fragment / JSON, in the spirit of the `#[RepresentAs]`
attribute in the reviewer's `quizler` project.

It solves duplicated `if ($request->ajax())` branching — a problem this
application does not have. There are two pages and two async interactions
(upload, delete), and both are better served by endpoints that return JSON while
jQuery updates the DOM. That is one shape per route, no fragment templates, no
resolver.

Cost: if a third page later needs server-rendered fragments, the branch appears
and this ADR gets superseded. Accepted — a layer of indirection is cheap to add
once it is earned and expensive to carry before then.

### ADR-009 — Session and cache driver default to `file`, not Laravel's `database`
**2026-09-10 · Accepted**
Laravel 11 scaffolds `SESSION_DRIVER`/`CACHE_STORE` as `database`. With that
default, the welcome page 500s until `php artisan migrate` has run, because the
`sessions`/`cache` tables do not exist yet — breaking TASK-001's Goal ("one
command → a working page") before AC-3 is even reached. `file` needs nothing
migrated. Cost: session/cache state is not queryable via the database. Acceptable
— this store has no auth (non-goal, `VALUE.md`) and no multi-instance deployment
to make file-based state a problem.

### ADR-010 — RabbitMQ `loopback_users.guest = false`
**2026-09-10 · Accepted**
RabbitMQ's default `guest` user authenticates only from the broker's own
loopback interface. A container reaching `rabbitmq` over the compose network is
not "localhost" to the broker, so the `guest`/`guest` credentials in
`.env.example` would otherwise never connect. Cost: `guest` becomes usable from
any host that can reach the broker, not just its own loopback. Acceptable — this
is a dev-only broker on the Compose network (`stack.md`), not exposed to the
host beyond the mapped ports.

### ADR-012 — Bootstrap and jQuery are vendored under `public/vendor`, not loaded from a CDN
**2026-09-10 · Accepted**
The reviewer gets a working page with no network and nothing to configure — the
same reasoning as ADR-005. Cost: two minified blobs in git that cannot be read
in a diff. Accepted, because the alternative is a demo that silently renders
unstyled and cannot upload at all when the CDN is unreachable or blocked.
Pinned versions: Bootstrap 5.3.3, jQuery 3.7.1.

### ADR-013 — Presenters live in `App\Http\Presenters`
**2026-09-10 · Accepted**
`DocumentRow` derives two display values (`timeRemainingLabel`,
`awaitingSweep`) from `expires_at`, and both must never drift from the
sweep's `WHERE expires_at <= NOW()`. Two alternatives were rejected: a Blade
helper (banned — no business logic in templates, `conventions.md` → DON'T),
and an `App\Domain` value object (rejected — it would have to import
`App\Models\Document`, an upward dependency against this file's layering
rule). Cost: a third directory under `app/Http`. Accepted — it makes the two
drift-prone rules unit-testable in isolation from the view.

### ADR-011 — DOCX admitted by its OOXML MIME type alone, no `application/zip` fallback
**2026-09-10 · Accepted**
`stack.md` flagged a risk: some `file`/magic databases detect a `.docx` as
`application/zip` rather than the specific OOXML type, because DOCX is a zip
container. Verified against the actual `app` image (`php:8.2-fpm`, `fileinfo`
built in) rather than assumed: `finfo` there correctly reports a real,
LibreOffice-produced DOCX as
`application/vnd.openxmlformats-officedocument.wordprocessingml.document`. So
`UploadPolicy` whitelists that exact MIME type and nothing broader — no
`application/zip` + extension fallback. Cost: a `file`/magic database that lacks
OOXML detection would wrongly reject real DOCX files. Accepted for this image;
if the deployment target's `fileinfo` ever regresses on this, that is a new
ticket, not silently widening the whitelist to `application/zip` (which would
admit *any* zip renamed `.docx`, defeating I-8).
