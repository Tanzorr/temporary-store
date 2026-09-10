# TASK-011 — Close the two paths that outlive the retention window

| Field | Value |
| :--- | :--- |
| **Status** | `done` |
| **Priority** | P1 (required for delivery) |
| **Value driver** | `V-1`, `V-2` |
| **Depends on** | `TASK-003`, `TASK-005` |
| **Created** | 2026-09-10 |

## Goal

Two bytes-outlive-their-window paths found in review are closed. A failed upload
leaves nothing on disk, and a Document past `expires_at` cannot be downloaded
even in the minutes before the sweep reaches it.

## Ontology Touchpoints

**Concepts:** `Document`, `StoredObject`, `UploadSession`, `RetentionPolicy`
**Invariants that must hold:** `I-1`, `I-2`, `I-3`, `I-6`
**New concepts introduced:** none.

## Acceptance Criteria

- [x] AC-1 — When the `documents` row cannot be written, `UploadDocument` leaves
      no bytes on the disk and the original failure reaches the caller. Covered
      by a test that fails the insert, not by inspection.
- [x] AC-2 — `GET /documents/{uuid}/download` returns `404` for a Document whose
      `expires_at` has passed but whose sweep has not run yet.
- [x] AC-3 — The list page renders no usable download control for such a
      Document; its row still shows *awaiting sweep* (TASK-005 behaviour is
      unchanged otherwise).
- [x] AC-4 — Manual deletion of an expired-but-unswept Document still succeeds
      and still produces exactly one `DeletionEvent` (I-3).
- [x] AC-5 — The download-window decision is recorded as an ADR.

## Out of Scope

- Reclaiming bytes already orphaned by a past failure — there is no such data in
  any environment that has run, and a disk-vs-table reconciler is its own ticket.
- A `max` length rule on `original_name`. Related, but it is an `UploadPolicy`
  change (I-1) and belongs with the policy, not here.
- Removing the unused `User`/auth scaffolding — cosmetic, separate commit.

## Notes

AC-1's failure is injected with a `Document::creating` listener that throws:
the dispatcher is rebuilt per test, so the listener cannot leak into another
test. Dropping the table would force a DDL commit and fight `RefreshDatabase`.

---

## Outcome

**Completed:** 2026-09-10
**What was built:**

| Change | Where |
| :--- | :--- |
| Stored object removed and the failure rethrown when the insert fails | `App\Services\UploadDocument` |
| `downloadable()` = `available()` + inside the window | `App\Models\Document` |
| `download()` narrowed to it; `destroy()` deliberately left on `available()` | `App\Http\Controllers\DocumentController` |
| Download control disabled for an `awaitingSweep` row | `documents/index.blade.php` |
| ADR-015 | `.ai/context/architecture.md` |

**Deviations from the plan:** the `User`/auth scaffolding removal, listed Out of
Scope as a separate commit, was done here after all — `config/auth.php`,
`App\Models\User`, `UserFactory`, both `ExampleTest`s and the seeder's user seed.
Nothing referenced them (`Auth::`/`auth.` appears nowhere in `app/`, `config/` or
`bootstrap/`) and the suite passes without them. `0001_01_01_000000_create_users_table`
is **kept**: it also creates `sessions`, and an environment that sets
`SESSION_DRIVER=database` would break without it.

**Invariants verified:**

- **I-3** — unchanged and re-checked: `DeleteDocument` is still the only deletion
  path (`rg 'delete\(|destroy\('` over `app/` gives its `Storage::delete` and the
  controller's call into it, nothing else), and `it_still_deletes_an_expired_document…`
  proves the narrowed download scope did not narrow deletion.
- **I-1, I-2** — untouched: the upload change is a `catch` around an unchanged
  insert; `RetentionPolicy` still computes every deadline.
- **I-6** — the sweep still selects on `available` + `expires_at`, unchanged;
  `downloadable()` is a read-path scope only.

**Verified how:** 53 tests / 167 assertions pass, `pint` clean on 65 files. Both
fixes were mutation-checked rather than assumed — reverting the `Storage::delete`
and re-widening `download()` to `available()` fails
`it_leaves_no_bytes_behind_when_the_document_row_cannot_be_written` and
`it_returns_404_for_an_expired_document_the_sweep_has_not_reached_yet`
respectively; restoring them passes.

**Follow-ups raised:**

- No `max` length on `original_name`, so a >255-char filename is a live
  `QueryException` on a `utf8mb4` column. It no longer orphans bytes, but it is a
  500 where a `422` belongs — an `UploadPolicy` rule (I-1), needs a ticket.
- The `curl`/`rabbitmqadmin` walkthrough in `README.md` is still unexecuted
  (inherited from TASK-010's Outcome, not addressed here).
