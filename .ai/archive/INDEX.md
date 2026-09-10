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
