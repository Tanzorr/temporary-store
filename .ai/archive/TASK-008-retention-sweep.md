# TASK-008 — Retention sweep command + scheduler

| Field | Value |
| :--- | :--- |
| **Status** | `done` |
| **Priority** | P1 |
| **Value driver** | **V-1** (reduced retention exposure), V-2 (zero-effort compliance) |
| **Depends on** | TASK-006, TASK-007 |
| **Created** | 2026-09-10 |

## Goal

Documents disappear on their own once their retention window elapses, with a
notification for each — without anyone doing anything. This is the ticket that
delivers the product's core promise.

## Ontology Touchpoints

**Concepts:** `tds:RetentionSweep`, `tds:RetentionPolicy`, `tds:RetentionExpiry`, `tds:Document`
**Invariants:** I-2 (deadline arithmetic in one place), **I-3**, **I-6**
(idempotent sweep), I-4
**New concepts:** none

## Acceptance Criteria

- [x] AC-1 — `php artisan documents:sweep-expired` selects `available` documents
      with `expires_at <= now()` and deletes each via
      `DeleteDocument::handle($doc, DeletionTrigger::RETENTION_EXPIRY, $sweepId)`.
      **No deletion logic of its own** (I-3).
- [x] AC-2 — The command is registered in the scheduler and appears in
      `php artisan schedule:list`.
- [x] AC-3 — The `scheduler` container runs it automatically; a document uploaded
      with a shortened TTL disappears without manual intervention (S-1).
- [x] AC-4 — Each run generates a `sweep_id`, recorded on every `DeletionEvent`
      it raises, and logs `ran_at`, `candidate_count`, `deleted_count`.
- [x] AC-5 — Running the sweep twice deletes nothing twice and publishes no
      duplicate messages (**I-6**, T-9).
- [x] AC-6 — A document that has not yet expired is untouched (T-8).
- [x] AC-7 — One document failing to delete does not abort the run; the failure
      is logged and the remaining candidates are processed. `deleted_count` then
      differs from `candidate_count` — the signal to alert on.
- [x] AC-8 — Documents are processed in batches (chunked query), so a large
      backlog does not exhaust memory.
- [x] AC-9 — Feature test T-7: with `Carbon::setTestNow()` advanced past the
      deadline, the sweep deletes the document, creates an event with
      `retention_expiry`, and publishes exactly one message. **No `sleep()`.**
- [x] AC-10 — The command is safe to run by hand at any time (documented in
      `stack.md`), which is how the retention path gets tested without waiting 24h.

## Out of Scope

- Per-document retention windows (open question **Q-3**). One global policy for now.
- Reclaiming orphaned bytes with no `Document` row — a separate concern, and it
  needs its own ticket if it turns out to happen.
- Pruning tombstone rows — open question **Q-1**.

## Notes

AC-9 and T-6 (from `TASK-005`) are together the acceptance test for the whole
project: they prove notification on *both* deletion paths, which is the
requirement the brief singles out.

The risk table in [`../business/VALUE.md`](../business/VALUE.md) names "scheduler
not running" as the worst failure mode, because nothing errors — files simply
live forever and V-1 is silently lost. AC-4's `ran_at` log line is what makes it
checkable by hand. It is **not** monitoring: a job that never runs produces no
log line and no failing exit code, so nothing fires. Real freshness alerting
needs a persisted last-run timestamp and is out of scope — say so rather than
implying the risk is closed.

---

## Outcome

**Completed:** 2026-09-10

**What was built:** `App\Services\SweepExpiredDocuments` — selects `available`
Documents with `expires_at <= now()` via `chunkById(200)` (AC-8), and for each
calls `DeleteDocument::handle($doc, DeletionTrigger::RETENTION_EXPIRY, $sweepId)`
inside a per-document try/catch, so one purge failure is logged and the run
continues (AC-7). Returns `App\Domain\Retention\SweepResult` (sweepId, ranAt,
candidateCount, deletedCount) — not persisted, per the ontology's "counts go to
the log" — which the service itself logs as `Retention sweep completed` (AC-4).
`App\Console\Commands\SweepExpiredDocuments` is a thin wrapper printing the same
result (AC-1, AC-10). Registered in `routes/console.php` via
`Schedule::command('documents:sweep-expired')->everyFiveMinutes()->withoutOverlapping()`
(AC-2); the `scheduler` container already runs `schedule:work` (TASK-001), so no
container change was needed.

**Deviations from the plan:**
- AC-9 names `Carbon::setTestNow()` as the mechanism; the tests instead use
  `Document::factory()->expired()`, which sets `expires_at` to an already-past
  timestamp at creation. Same effect — deterministic, no `sleep()` — without
  freezing global time across the test. Noted rather than silently substituted.
- Idempotency (AC-5/I-6) is proved as an outcome, not a single mechanism: a
  second sweep's own query already excludes a document once its status flips to
  `deleted` (the first line of defence), and `DeleteDocument`'s existing
  de-dup (TASK-006) is the second, in case a candidate is ever re-selected. The
  test asserts both — `second.candidateCount === 0` — rather than assuming which
  one fired.
- `withoutOverlapping()` and the 5-minute frequency are not named in the ticket;
  added as the obvious guard against a slow run overlapping the next tick.
  Neither is configurable — no env key for sweep frequency exists in
  `stack.md`, so one was not invented.

**Invariants verified:**
- **I-2** — the sweep's `WHERE` clause reads `expires_at`, never recomputes a
  deadline; feature test confirms an unexpired document is untouched (T-8).
- **I-3** — every candidate that deletes successfully goes through
  `DeleteDocument`, the sweep's only deletion call; live run below confirms
  exactly one `DeletionEvent` and one published message per swept document.
- **I-6** — `running_the_sweep_twice_...` test: second run finds zero
  candidates, zero new events, zero new messages.

**Live verification (not just the test suite):** created a real expired
`Document` via `tinker` with bytes on the `local` disk, left the stack running
unmodified, and polled it rather than running the command by hand. The
`scheduler` container's own `schedule:work` picked it up at its next tick
(`local.INFO` log entries, not `testing.INFO` — confirms this ran in the real
environment, not a test process):

```
[2026-09-10 12:45:00] local.INFO: Retention sweep completed
  {"sweep_id":"389ec75a-...","ran_at":"2026-09-10T12:45:00+00:00",
   "candidate_count":1,"deleted_count":1}
```

`DeletionEvent` for that document: `trigger=retention_expiry`,
`initiator=scheduler`, `sweep_id` matching the log line. `rabbitmqadmin get
queue=document.deletions` read back exactly one message,
`"trigger":"retention_expiry"`, `event_id` matching the `DeletionEvent` uuid —
confirming AC-2, AC-3, AC-4 end-to-end, not merely `documents:sweep-expired`
run by hand.

**Follow-ups raised:** none. `TASK-009` and `TASK-010` are now unblocked.
