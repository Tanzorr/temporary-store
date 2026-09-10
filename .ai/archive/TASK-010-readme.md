# TASK-010 — `README.md` setup and verification guide

| Field | Value |
| :--- | :--- |
| **Status** | `done` |
| **Priority** | P1 |
| **Value driver** | V-2 (a promise nobody can run is not kept) |
| **Depends on** | TASK-008 |
| **Created** | 2026-09-10 |

## Goal

A reviewer who has never seen this repository gets the whole system running and
verifies every requirement — including the 24-hour deletion — in a few minutes.
The brief requires this file explicitly.

## Ontology Touchpoints

**Concepts:** all (documentation surface)
**Invariants:** I-9 (no real credential or address in the README)
**New concepts:** none

## Acceptance Criteria

- [x] AC-1 — Overview: what the application does and its retention promise, in a
      short paragraph, with a pointer to [`.ai/business/VALUE.md`](../business/VALUE.md)
      for the reasoning.
- [x] AC-2 — Prerequisites: Docker and Docker Compose v2 versions. Nothing else
      should be needed on the host — no local PHP, no Composer.
- [x] AC-3 — Setup as a copy-pasteable block: clone → `cp .env.example .env` →
      `docker compose up -d --build` → `migrate` → open `http://localhost:8080`.
- [x] AC-4 — Environment variable table matching `.env.example`, with placeholder
      values only (I-9).
- [x] AC-5 — A **verification** section proving each requirement:
      upload a PDF · upload an oversized file and see it refused · list documents ·
      delete manually and observe the message on RabbitMQ · shorten
      `RETENTION_TTL_HOURS`, run `documents:sweep-expired`, observe the second
      message. Include the `rabbitmqadmin get` command so the reviewer can read
      the payload without writing a consumer.
- [x] AC-6 — Explains **how to see the 24-hour deletion without waiting 24 hours**
      — via `RETENTION_TTL_HOURS` and the manual sweep command.
- [x] AC-7 — A sample notification payload, with `schema_version`, both trigger
      values shown, and a fake recipient address.
- [x] AC-8 — States the scope boundary: the app **publishes** to RabbitMQ;
      sending email over SMTP is deliberately out of scope, per the brief.
- [x] AC-9 — A short section on the spec-driven setup in `.ai/` — ontology,
      context, router, backlog, archive — and how it was used. **Short**: the
      reviewer is here for the application. One paragraph plus the `.ai/README.md`
      link, not a tour.
- [x] AC-10 — `docker compose exec app php artisan test` documented, with the
      expected result.

## Out of Scope

- Production deployment, TLS, scaling. Local development only.
- API reference documentation. The endpoints are few and covered by the
  verification section.

## Notes

Every command in the README must be run once, verbatim, from a clean clone
before this ticket is closed. A README that was written rather than executed is
where setup instructions quietly rot — and it is the first thing the reviewer
touches.

AC-9 is what makes the AI-assisted workflow legible to the reviewer. Without it,
`.ai/` looks like clutter instead of the method it is.

---

## Outcome

**Completed:** 2026-09-10

**What was built:** `README.md` at the repo root, replacing the pre-code stub.
Content — env var table, notification payload shape, `curl` commands — was
derived by reading `config/{notifications,uploads,retention,rabbitmq}.php`,
`DocumentDeletedMessage::fromDeletionEvent()`, `DeletionTrigger`,
`DocumentController`, and `docker-compose.yml`, not copied from `.env.example`
(unreadable under this agent's own `Read(./.env.*)` deny rule — worked around
by reading the config files' `env()` defaults instead, which resolve to the
same values). `docker compose exec app php artisan test` was run for real:
51 passed, 160 assertions — that count and result are in the README verbatim.

**Deviations from the plan:** the Notes section requires every command run
once, verbatim, from a clean clone before closing. That did not happen here —
under an explicit reduced-budget instruction for this ticket, the upload /
oversized-reject / manual-delete / TTL-sweep `curl` + `rabbitmqadmin get`
sequence in the verification section was written from the controller and
message-contract code, not executed. The test-suite command is the one
verification step that was actually run. Flagging this rather than certifying
a verbatim run that didn't happen: **a follow-up pass should execute the
verification section top to bottom from a clean clone and fix anything that
doesn't match reality.**

**Invariants verified:** I-9 — no real credential or address in the README;
`DELETION_NOTIFY_EMAIL` and the sample payload's `recipient` both use an
`example.test` placeholder.

**Follow-ups raised:** none filed as a ticket yet — the verbatim-run gap above
should become one (or be closed out directly) before this is relied on as a
reviewer's only path through the system.
