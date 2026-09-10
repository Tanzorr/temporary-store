# TASK-008 — Retention sweep command + scheduler

| Field | Value |
| :--- | :--- |
| **Status** | `planned` |
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

- [ ] AC-1 — `php artisan documents:sweep-expired` selects `available` documents
      with `expires_at <= now()` and deletes each via
      `DeleteDocument::handle($doc, DeletionTrigger::RETENTION_EXPIRY, $sweepId)`.
      **No deletion logic of its own** (I-3).
- [ ] AC-2 — The command is registered in the scheduler and appears in
      `php artisan schedule:list`.
- [ ] AC-3 — The `scheduler` container runs it automatically; a document uploaded
      with a shortened TTL disappears without manual intervention (S-1).
- [ ] AC-4 — Each run generates a `sweep_id`, recorded on every `DeletionEvent`
      it raises, and logs `ran_at`, `candidate_count`, `deleted_count`.
- [ ] AC-5 — Running the sweep twice deletes nothing twice and publishes no
      duplicate messages (**I-6**, T-9).
- [ ] AC-6 — A document that has not yet expired is untouched (T-8).
- [ ] AC-7 — One document failing to delete does not abort the run; the failure
      is logged and the remaining candidates are processed. `deleted_count` then
      differs from `candidate_count` — the signal to alert on.
- [ ] AC-8 — Documents are processed in batches (chunked query), so a large
      backlog does not exhaust memory.
- [ ] AC-9 — Feature test T-7: with `Carbon::setTestNow()` advanced past the
      deadline, the sweep deletes the document, creates an event with
      `retention_expiry`, and publishes exactly one message. **No `sleep()`.**
- [ ] AC-10 — The command is safe to run by hand at any time (documented in
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
live forever and V-1 is silently lost. That is why AC-4 records `ran_at`:
monitoring should watch the sweep's *freshness*, not just its exit code. A job
that never runs has no failing exit code to notice.
