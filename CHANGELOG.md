# Temporary Document Store

Notable changes, newest first. Every entry references the ticket that produced
it, so a reader can go from a line here to its acceptance criteria in
[`.ai/archive/`](.ai/archive/INDEX.md) without asking anyone.

Format follows [Keep a Changelog](https://keepachangelog.com/). Adding an entry
is part of the Definition of Done in
[`.ai/context/conventions.md`](.ai/context/conventions.md).

## Unreleased

**Added**
- TASK-007: `App\Listeners\DispatchDeletionNotification` turns every
  `DocumentDeleted` into a queued `App\Jobs\PublishDeletionNotification`
  (ADR-014), which publishes a `NotificationMessage` to RabbitMQ through
  `App\Infrastructure\Rabbit\RabbitPublisher` (ADR-004). The message id is
  the `DeletionEvent` uuid (I-5); the exchange and queue are declared durable
  and the message persistent (AC-6); the job retries with backoff on a
  broker outage without creating a second `DeletionEvent` (I-10). Same path
  for both `DeletionTrigger` values — the publisher carries no branch on
  which one it is (AC-10). New dependency: `php-amqplib/php-amqplib`.
- TASK-005: `GET /documents` — the operator's document list, with download and
  manual delete. Available documents only (T-5), paginated 25 per page,
  newest-first with an `id` tie-break for a stable order within one second.
  `App\Http\Presenters\DocumentRow` (ADR-013) derives size/type labels and
  the *awaiting sweep* / time-remaining values from `expires_at`, so the view
  makes no expiry decision of its own (I-7, I-2). Delete goes through
  `DeleteDocument::handle(..., DeletionTrigger::MANUAL_DELETION)` and nothing
  else (I-3); the row is removed client-side by jQuery after a `window.confirm()`
  and a JSON `DELETE`, no page reload. Download streams the file under its
  original, escaped name with the stored `mime_type` as `Content-Type`.
- TASK-006: `App\Services\DeleteDocument`, the only place a `Document` may be
  deleted (I-3). One transaction creates the `DeletionEvent`, purges the
  `StoredObject`, then marks the document deleted — rolling back all three if
  the purge fails (T-12), and returning the existing event unchanged on a
  repeat call instead of a second one (I-6). Dispatches
  `App\Events\DocumentDeleted` from `DB::afterCommit`, never from inside the
  transaction (I-4), for `TASK-007`'s publisher to consume. The trigger
  (`App\Domain\Deletion\DeletionTrigger`: `manual_deletion` |
  `retention_expiry`) is a required argument with no default.
- TASK-004: `GET /` — Bootstrap + jQuery uploader with a real progress bar.
  Uploads go through `XMLHttpRequest` with `upload.onprogress`, no page
  reload; every failure mode (`too_large`, `unsupported_type`, `corrupt`,
  `413`/non-JSON body, network error) maps to a specific message.
  `original_name` reaches the DOM only through `.text()`/`{{ }}` (I-7).
  Bootstrap and jQuery are vendored under `public/vendor`, not loaded from a
  CDN (ADR-012).
- TASK-003: `POST /documents` — async upload endpoint. Validation
  (size, content-detected MIME type) lives in `UploadDocumentRequest` and
  rejects server-side regardless of the client's `Content-Type` or filename
  (I-1, I-8); `UploadDocument` stores accepted files under a generated
  `{uuid}.{extension}` name, so the original filename can never reach the
  filesystem as a path (I-7), and reads the expiry from `RetentionPolicy`
  (I-2). Covered by feature tests for T-1, T-2, T-3, T-4 and T-15.
- TASK-002: `documents` table, `App\Models\Document`, `RetentionPolicy` and
  `DocumentFactory`. `RetentionPolicy` is the only place an expiry is
  computed (I-2), proven by a unit test covering the default TTL and a
  changed config value.
- TASK-001: Laravel 11 + Docker Compose skeleton — `app`, `web`, `mysql`,
  `rabbitmq`, `queue` and `scheduler` as six healthy, independently
  restarting services behind one `docker compose up -d --build`. Domain
  config (`retention.php`, `uploads.php`, `notifications.php`,
  `rabbitmq.php`) reads every value through `env()`.
- Spec-driven development layer in `.ai/`: business value, domain ontology,
  code context, router modes, ticketed backlog and archive.
- `CLAUDE.md` entry point routing into the ontology, the working modes and the
  tool rules.
- `.claude/settings.json` permission policy: read-broad, ask on mutating
  operations, deny secrets and destructive commands.
- `.editorconfig` covering PHP, Blade, JS, YAML, Compose files and Dockerfiles.

**Changed**
- Ontology is one Markdown file (`.ai/context/ontology.md`) instead of a Turtle
  source plus a prose projection of it. Nothing parsed the Turtle, so the second
  copy could only drift.
- Router reduced to the two modes that change something or judge what changed:
  `implement` and `review`.

**Removed**
- `TASK-011` (project-local `msearch`/`mread`/`mreplace` tooling) — it shipped
  nothing the reviewer runs and duplicated tools the agent already has.
- ADR-008's representation resolver, in favour of `ADR-007`: JSON endpoints are
  the only async surface, and jQuery updates the DOM.

**Fixed**
- `RetentionSweep` no longer implies a table that does not exist; `sweep_id` is
  documented as a correlation id, and `VALUE.md` states plainly that the
  "scheduler not running" risk is only partly mitigated.
- MySQL is published on host port `33061`, avoiding a clash with a local server.

---

## Conventions

- One entry per user-visible change. Internal refactors that change nothing
  observable belong in the ticket's outcome note, not here.
- Prefix each entry with its ticket: `TASK-006: Deletion events and the single
  deletion path.`
- Write for someone who did not do the work. "Fix bug" tells them nothing;
  "Sweep no longer publishes a duplicate notification when run twice" does.
- Sections: **Added**, **Changed**, **Fixed**, **Removed**. Omit empty ones once
  the file has real content.
