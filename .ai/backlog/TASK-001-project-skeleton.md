# TASK-001 — Laravel + Docker Compose skeleton

| Field | Value |
| :--- | :--- |
| **Status** | `planned` |
| **Priority** | P0 |
| **Value driver** | V-4 (nothing is usable until the stack runs) |
| **Depends on** | — |
| **Created** | 2026-09-10 |

## Goal

A reviewer clones the repository, runs one command, and reaches a working Laravel
page with MySQL and RabbitMQ up. Everything else in the backlog is blocked on this.

## Ontology Touchpoints

**Concepts:** `tds:MessageQueue` (broker provisioned, not yet used)
**Invariants:** I-9 (no secret committed)
**New concepts:** none

## Acceptance Criteria

- [ ] AC-1 — `docker compose up -d --build` starts six services: `app`, `web`,
      `mysql`, `rabbitmq`, `queue`, `scheduler` (see [`../context/stack.md`](../context/stack.md)).
- [ ] AC-2 — `http://localhost:8080` returns the Laravel welcome page.
- [ ] AC-3 — `php artisan migrate` succeeds against the `mysql` service.
- [ ] AC-4 — RabbitMQ management UI is reachable at `http://localhost:15672`.
- [ ] AC-5 — `queue` and `scheduler` run as **separate containers**, and
      `docker compose ps` shows both healthy.
- [ ] AC-6 — `.env.example` contains every key from `stack.md` with placeholder
      values; `.env` is gitignored and contains no real address or credential.
- [ ] AC-7 — Config files `retention.php`, `uploads.php`, `notifications.php`,
      `rabbitmq.php` exist and read their values via `env()` — the only place
      `env()` may be called.
- [ ] AC-8 — `php artisan test` runs (the default suite may be all that exists).

## Out of Scope

- Any domain table or model — `TASK-002`.
- Any RabbitMQ publishing code — `TASK-007`. This ticket only provisions the broker.
- README content — `TASK-010`.

## Notes

Set PHP `upload_max_filesize` and `post_max_size` above `UPLOAD_MAX_SIZE_BYTES`
in the image's php.ini. If they are lower, PHP rejects a large upload before
Laravel sees it and the error surfaces as an empty request rather than a
validation message — a debugging trap listed in [`../router/debug.md`](../router/debug.md).

Use a queue driver backed by the database or Redis for Laravel's own job queue.
RabbitMQ here is the **outbound notification bus**, not Laravel's queue backend;
conflating the two makes the notification path impossible to test in isolation.
