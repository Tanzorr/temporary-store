# TASK-006 — `DeletionEvent`, `DeleteDocument`, trigger enum

| Field | Value |
| :--- | :--- |
| **Status** | `done` |
| **Priority** | P1 |
| **Value driver** | V-3 (auditable deletion), V-5 (operator control) |
| **Depends on** | TASK-002 |
| **Created** | 2026-09-10 |

## Goal

Establish the single code path through which a `Document` may ever be deleted,
recording why. This is the structural precondition for the brief's requirement
that **both** manual and automatic deletion notify — everything downstream hangs
off this one service.

## Ontology Touchpoints

**Concepts:** `tds:DeletionEvent`, `tds:DeletionTrigger`, `tds:Document`, `tds:StoredObject`
**Invariants:** **I-3** (one event per deletion), I-4 (side effects after commit),
purge-failure rule
**New concepts:** none — `DeletionEvent` and `DeletionTrigger` are already in
[`ontology.md`](../context/ontology.md)

## Acceptance Criteria

- [x] AC-1 — Migration creates `deletion_events`: `id`, `uuid`, `document_id`,
      `trigger`, `initiator`, `occurred_at`, `sweep_id` (nullable), timestamps.
      `sweep_id` is a **correlation id, not a foreign key** — there is no
      `retention_sweeps` table and `RetentionSweep` is not persisted. It groups
      the events one run raised so a log line and a set of rows can be tied
      together; it is `null` for manual deletions.
- [x] AC-2 — `App\Domain\Deletion\DeletionTrigger` is a backed PHP enum with
      exactly `MANUAL_DELETION = 'manual_deletion'` and
      `RETENTION_EXPIRY = 'retention_expiry'`, matching the ontology's closed
      enumeration and its `triggerCode` values.
- [x] AC-3 — `App\Services\DeleteDocument::handle(Document $document,
      DeletionTrigger $trigger, ?string $sweepId = null): DeletionEvent` exists
      and the trigger is a **required** argument with no default.
- [x] AC-4 — Within one `DB::transaction`, `handle()` creates the `DeletionEvent`,
      purges the `StoredObject`, and sets the document's status to `deleted`.
- [x] AC-5 — If the purge fails, the transaction rolls back: the document stays
      `available` and no event row survives. Bytes never outlive their record.
- [x] AC-6 — Deleting an already-deleted document is a no-op returning the
      existing event — no second event, no second notification (supports I-6).
- [x] AC-7 — `DeleteDocument` emits an application event or dispatch hook
      **after commit** (`DB::afterCommit`) for `TASK-007` to consume. Nothing is
      published from inside the transaction (I-4).
- [x] AC-8 — Log lines include document uuid, event uuid and trigger.
- [x] AC-9 — Feature tests: manual delete creates exactly one event with the
      right trigger; purge failure aborts (T-12); double delete creates one event.

## Out of Scope

- Publishing to RabbitMQ — `TASK-007`. This ticket produces the event; it does
  not know a broker exists.
- The delete button in the UI — `TASK-005`.
- The scheduled sweep — `TASK-008`.

## Notes

The single most important rule in the repository: **no other code may delete a
`Document`** — not a controller, not an observer, not a console one-liner
(`conventions.md` → DON'T). Both later call sites (`TASK-005` manual,
`TASK-008` sweep) pass through here, which is what makes I-3 structurally true
rather than a thing two code paths must each remember.

ADR-001 explains why the event is a table rather than a `deleted_at` column;
ADR-003 explains why publication is deferred out of the transaction. Read both
before changing this design.

---

## Outcome

**Completed:** 2026-09-10
**What was built:** `deletion_events` migration (`document_id` **unique**, not
just indexed — enforces I-3's "exactly one event per document" at the data
layer, not only in the service); `App\Domain\Deletion\DeletionTrigger`;
`App\Models\DeletionEvent` casting `trigger` to the enum, with a `document()`
relation; `App\Services\DeleteDocument::handle()` — create event → purge →
mark deleted in one `DB::transaction`, throwing (and so rolling back) on a
failed purge; `App\Events\DocumentDeleted`, dispatched from `DB::afterCommit`
alongside the required log line, for `TASK-007`'s listener to turn into a
queued publish. Idempotency (AC-6) is decided by querying for an existing
`DeletionEvent` by `document_id` before opening the transaction, not by
trusting the passed-in `Document`'s in-memory `status` — a stale second
instance of the same row still resolves correctly.
**Deviations from the plan:** two judgment calls the ticket left open,
resolved here rather than left for review to wonder about:
  - `initiator` isn't populated by a caller argument — `handle()`'s signature
    is fixed by AC-3 with none. It's derived 1:1 from the trigger
    (`manual_deletion` → `operator`, `retention_expiry` → `scheduler`), the
    only two actors this system has (no auth — an explicit non-goal).
  - AC-7's "application event or dispatch hook" is a plain Laravel event
    (`DocumentDeleted`), not a direct dispatch of `PublishDeletionNotification`.
    `architecture.md`'s diagram names that job as what ultimately runs after
    commit; a job class TASK-006 can't finish (it would need
    `NotificationPublisher`, `TASK-007`'s AC-1) would be a half-built
    implementation. `TASK-007` adds the listener that turns this event into
    that job.
**Invariants verified:** I-3 — `DeleteDocumentTest` proves exactly one event
per delete, a unique DB constraint on `document_id` backs it structurally. I-4
— purge-failure test asserts zero events, `Available` status, and no
`DocumentDeleted` dispatch after rollback. I-6 — a second `handle()` call
against a freshly refetched `Document` returns the same event and dispatches
nothing further.
**Follow-ups raised:** none — `TASK-007` and `TASK-008` proceed as already
scoped in the backlog.
