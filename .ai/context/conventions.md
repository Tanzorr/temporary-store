# Code Conventions

Rules that apply to every line written in this repository. Where a rule has a
reason, the reason is given — a rule you understand is one you can apply to a
case it does not literally cover.

## Language & Style

- **PHP 8.2+**, `declare(strict_types=1);` at the top of every PHP file.
- **PSR-12**, enforced by Laravel Pint. Run `./vendor/bin/pint` before finishing.
- Type every parameter, property and return. `mixed` needs a comment justifying it.
- Constructor property promotion for dependencies. No setter injection.
- `final` by default on concrete classes. Open one up only when something extends it.
- Enums over string constants for closed sets — `DeletionTrigger` is an enum
  precisely because the ontology declares it a closed enumeration.

## Naming

| Thing | Convention | Example |
| :--- | :--- | :--- |
| Class | `StudlyCase`, singular | `DeletionEvent` |
| Service (an action) | Verb phrase | `DeleteDocument`, `SweepExpiredDocuments` |
| Interface | No `I` prefix, no `Interface` suffix where a noun reads well | `NotificationPublisher` |
| Method | `camelCase`; a service's entry point is `handle()` | `handle()` |
| DB table | `snake_case`, plural | `deletion_events` |
| DB column | `snake_case`; timestamps end in `_at` | `expires_at` |
| Config key | `snake_case` under a domain file | `retention.ttl_hours` |
| Route name | `dot.case` | `documents.destroy` |
| Blade view | `kebab-case` | `documents/index.blade.php` |
| Test | `it_<behaviour>` | `it_publishes_a_notification_on_manual_deletion` |

**Names come from the ontology.** If the ontology says `Document`, the class is
not `File`, `Upload`, or `Attachment`. When code and ontology disagree on a name,
one of them is wrong — fix it, do not translate at the boundary. Introducing a
concept the ontology does not contain means updating
[`ontology/index.ttl`](ontology/index.ttl) in the same change.

## Structure

```
app/
  Console/Commands/       Thin CLI entry points → call a service
  Domain/                 Framework-free: entities, value objects, enums, interfaces
    Deletion/  Notification/  Retention/  Storage/  Upload/
  Http/
    Controllers/          Thin → validate, call a service, return a response
    Requests/             All validation rules live here, not in controllers
  Infrastructure/         Adapters: Rabbit, filesystem
  Jobs/                   Queued work
  Models/                 Eloquent only
  Services/               Application services — the domain rules
config/                   retention.php, uploads.php, notifications.php, rabbitmq.php
```

## DO

- **Put domain rules in services.** A controller that computes an expiry date is
  misplaced code.
- **Own the transaction in the service.** One `DB::transaction` per business
  operation, not one per model write.
- **Read configuration through `config()`**, populated from `env()` in
  `config/*.php` only. `env()` elsewhere returns `null` under `config:cache`.
- **Dispatch side effects after commit.** `DB::afterCommit`, or a queued job.
- **Fail loudly.** An unexpected state throws. A swallowed exception in the
  deletion path is a silent breach of the product's core promise.
- **Log with context**: document id, event id, trigger. A log line without them
  cannot be correlated to a deletion.
- **Use UTC everywhere.** Store, compare and publish in UTC; convert only when
  rendering.
- **Name migrations for what they do**: `create_deletion_events_table`.

## DON'T

- **Don't delete a `Document` outside `DeleteDocument`.** Not in a controller,
  not in an observer, not in a `tinker` one-liner that becomes a script. This is
  invariant I-3 and it is the single rule most worth protecting.
- **Don't trust client input about content.** Not the `Content-Type` header, not
  the extension, not a client-side size check (I-8).
- **Don't use `originalName` as a path segment** or drop it unescaped into a
  header or a Blade template (I-7). `{{ }}` escapes; `{!! !!}` does not — so
  don't use `{!! !!}` on user data at all.
- **Don't publish inside a transaction** (I-4).
- **Don't recompute the retention deadline anywhere but `RetentionPolicy`** (I-2).
- **Don't put business logic in Blade.** A template that decides whether a
  document is expired is a bug waiting to disagree with the sweep.
- **Don't add a package to solve a five-line problem.** Every dependency is a
  thing the reviewer has to trust.
- **Don't commit `.env`**, real email addresses, or credentials (I-9).
- **Don't leave `dd()`, `dump()`, `var_dump()` or commented-out code** in a change.
- **Don't widen scope silently.** Auth, previews, versioning and multi-tenancy are
  explicit non-goals in [`../business/VALUE.md`](../business/VALUE.md). If a task
  seems to need one, stop and raise it as a ticket.

## Error Handling

- Validation failures → `422` with a machine-readable code matching
  `UploadSession.rejectionReason` (`too_large`, `unsupported_type`, `corrupt`).
- Missing document → `404`. Never leak whether an id ever existed.
- Infrastructure failures (disk, broker) → let them bubble; the queue retries.
  Do not catch-and-continue in a path that must not silently no-op.

## Commits

- Imperative subject, ≤ 72 chars: `Add deletion event and notification publisher`.
- Reference the ticket in the body: `Ticket: TASK-004`.
- One logical change per commit. A commit that both refactors and adds a feature
  cannot be reviewed or reverted cleanly.

## Definition of Done

A change is not done until all of these hold:

1. Code follows the rules above; `pint` is clean.
2. Tests cover the new behaviour, including its failure mode.
3. `php artisan test` passes in full.
4. Every invariant the change touches is still true — say which, in the ticket.
5. If a decision was made, it is an ADR in [`architecture.md`](architecture.md).
6. If a concept was added or renamed, `ontology/index.ttl` is updated.
7. The ticket has moved to [`../archive/`](../archive/INDEX.md) with an outcome note.
