# Archive

Completed tickets. A ticket moves here from [`../backlog/`](../backlog/INDEX.md)
once its Definition of Done is satisfied in full
([`../context/conventions.md`](../context/conventions.md)).

## Completed

| ID | Title | Completed | Value | Outcome |
| :--- | :--- | :--- | :--- | :--- |
| [TASK-001](TASK-001-project-skeleton.md) | Laravel + Docker Compose skeleton | 2026-09-10 | V-4 | Six healthy services; verified against a from-scratch, vendor-free clone |
| [TASK-002](TASK-002-document-persistence.md) | `documents` table, model, factory | 2026-09-10 | V-3 | `RetentionPolicy` is the sole source of `expiresAt`; verified against real MySQL |
| [TASK-003](TASK-003-upload-endpoint.md) | Async upload endpoint with server-side policy | 2026-09-10 | V-4, V-1 | Content-detected MIME whitelist, uuid-based storage names; verified end-to-end through nginx + php-fpm, not just feature tests |
| [TASK-004](TASK-004-upload-ui.md) | Bootstrap + jQuery uploader with progress | 2026-09-10 | V-4 | One upload function shared by input and drop zone; every manual-verification row driven against a real headless browser, not eyeballed |
| [TASK-006](TASK-006-deletion-core.md) | `DeletionEvent`, `DeleteDocument`, trigger enum | 2026-09-10 | V-3, V-5 | Single deletion path with a DB-unique `document_id` backing I-3; purge failure rolls back the whole transaction |
| [TASK-005](TASK-005-crud-page.md) | Document list, download, manual delete UI | 2026-09-10 | V-5 | `DocumentRow` (ADR-013) keeps expiry logic out of Blade; all 5 manual-verification rows driven against a real headless browser |
| [TASK-007](TASK-007-rabbitmq-publisher.md) | Notification publisher + queued job | 2026-09-10 | V-3, V-6 | One publish path for both `DeletionTrigger`s (ADR-014); broker-outage retry verified live — backoff fires at 10s/30s/60s, no duplicate `DeletionEvent` |
| [TASK-008](TASK-008-retention-sweep.md) | Retention sweep command + scheduler | 2026-09-10 | V-1, V-2 | `SweepExpiredDocuments` adds no deletion logic of its own (I-3); idempotent by query exclusion + `DeleteDocument` de-dup (I-6); scheduler container's own `schedule:work` verified sweeping a real document live, not just run by hand |
| [TASK-009](TASK-009-test-suite.md) | Test suite covering T-1…T-15 | 2026-09-10 | V-3 | All 15 rows already covered by prior tickets; verified with a real mutation test — commenting out the publish call fails T-6/T-7 (3 of 5 tests), confirming they're load-bearing, not decorative |
| [TASK-011](TASK-011-retention-leak-fixes.md) | Close the two paths that outlive the retention window | 2026-09-10 | V-1, V-2 | Orphaned bytes on a failed insert are removed; `expires_at`, not the sweep, ends availability (ADR-015) — both fixes mutation-checked, not assumed. Also drops the unused auth scaffolding |
| [TASK-010](TASK-010-readme.md) | `README.md` setup and verification guide | 2026-09-10 | V-2 | Env vars and payload shape derived from config/code, not `.env.example` (denied to the agent by its own permission rules); `artisan test` run for real (51 passed) — the `curl`/`rabbitmqadmin` verification walkthrough was **not** executed verbatim, flagged as an open follow-up in the ticket's Outcome |

## How to Archive a Ticket

1. Fill in the **Outcome** section of the ticket file:
   - what was actually built
   - deviations from the plan, or "none"
   - which invariants were verified, and how
   - follow-ups raised (as new backlog tickets, with their IDs)
2. Set `Status: done`.
3. `git mv .ai/backlog/TASK-NNN-*.md .ai/archive/`
4. Remove the row from the backlog index; add it here.

## Why This Exists

The archive is the project's decision record at ticket granularity. Six months
later the useful question is rarely "what does this code do" — git and the code
answer that — but "what was this trying to achieve, and what was rejected along
the way". The outcome notes answer it.

An archived ticket is **immutable**. If its decision was wrong, that is a new
ticket referencing this one, not an edit to history. Amending a closed ticket to
match what actually shipped destroys the only record that the plan and the
outcome ever differed — which is exactly the signal worth keeping.
