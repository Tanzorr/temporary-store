# TASK-010 — `README.md` setup and verification guide

| Field | Value |
| :--- | :--- |
| **Status** | `planned` |
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

- [ ] AC-1 — Overview: what the application does and its retention promise, in a
      short paragraph, with a pointer to [`.ai/business/VALUE.md`](../business/VALUE.md)
      for the reasoning.
- [ ] AC-2 — Prerequisites: Docker and Docker Compose v2 versions. Nothing else
      should be needed on the host — no local PHP, no Composer.
- [ ] AC-3 — Setup as a copy-pasteable block: clone → `cp .env.example .env` →
      `docker compose up -d --build` → `migrate` → open `http://localhost:8080`.
- [ ] AC-4 — Environment variable table matching `.env.example`, with placeholder
      values only (I-9).
- [ ] AC-5 — A **verification** section proving each requirement:
      upload a PDF · upload an oversized file and see it refused · list documents ·
      delete manually and observe the message on RabbitMQ · shorten
      `RETENTION_TTL_HOURS`, run `documents:sweep-expired`, observe the second
      message. Include the `rabbitmqadmin get` command so the reviewer can read
      the payload without writing a consumer.
- [ ] AC-6 — Explains **how to see the 24-hour deletion without waiting 24 hours**
      — via `RETENTION_TTL_HOURS` and the manual sweep command.
- [ ] AC-7 — A sample notification payload, with `schema_version`, both trigger
      values shown, and a fake recipient address.
- [ ] AC-8 — States the scope boundary: the app **publishes** to RabbitMQ;
      sending email over SMTP is deliberately out of scope, per the brief.
- [ ] AC-9 — A short section on the spec-driven setup in `.ai/` — ontology,
      context, router, backlog, archive — and how it was used.
- [ ] AC-10 — `docker compose exec app php artisan test` documented, with the
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
