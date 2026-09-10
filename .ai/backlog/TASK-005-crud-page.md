# TASK-005 — Document list, download, manual delete UI

| Field | Value |
| :--- | :--- |
| **Status** | `planned` |
| **Priority** | P1 |
| **Value driver** | V-5 (operator control) |
| **Depends on** | TASK-006 |
| **Created** | 2026-09-10 |

## Goal

A separate management page where an operator sees what is currently held, how
long each document has left, can download it, and can delete it early — with that
deletion travelling the same path as an automatic one.

## Ontology Touchpoints

**Concepts:** `tds:Document`, `tds:DeletionEvent`, `tds:ManualDeletion`
**Invariants:** **I-3** (manual delete goes through `DeleteDocument`), I-7
(escaped filenames), I-2 (remaining time derived from `expires_at`, not recomputed)
**New concepts:** none

## Acceptance Criteria

- [ ] AC-1 — `GET /documents` renders a Bootstrap table of `available` documents:
      original name, size, type, uploaded at, expires at, time remaining.
- [ ] AC-2 — Deleted documents are excluded from the list (T-5).
- [ ] AC-3 — The list is paginated; ordering is newest first.
- [ ] AC-4 — `GET /documents/{uuid}/download` streams the file with the original
      name in `Content-Disposition`, correctly escaped (I-7). An unknown or
      deleted uuid returns `404` — never a message revealing that it once existed.
- [ ] AC-5 — `DELETE /documents/{uuid}` calls
      `DeleteDocument::handle($doc, DeletionTrigger::MANUAL_DELETION)` and
      nothing else. The controller contains no deletion logic (**I-3**).
- [ ] AC-6 — Deletion happens asynchronously via jQuery, with a confirmation
      dialog, and the row disappears without a page reload.
- [ ] AC-7 — "Time remaining" is rendered from `expires_at`; the template never
      decides expiry itself (`conventions.md` → DON'T: no business logic in Blade).
- [ ] AC-8 — A document whose deadline has passed but which the sweep has not yet
      collected is shown as `expiring` rather than as a normal entry — the list
      must not imply a promise the sweep is about to break.
- [ ] AC-9 — Feature test T-5, plus a test asserting the manual delete route
      produces a `DeletionEvent` with trigger `manual_deletion` (part of T-6).
- [ ] AC-10 — Controller actions return an array of data, not a `Response`. A
      `RespondsWithView` trait resolves it into the full Blade view, the fragment
      view, or JSON — per **ADR-008**. No action inspects the request to decide
      its own shape, and no action contains an `if ($request->ajax())` branch.
- [ ] AC-11 — The list is defined once and rendered by both the full page and the
      fragment. A feature test requests the same route with and without
      `X-Requested-With: XMLHttpRequest` and asserts both carry the same rows.

## Out of Scope

- Editing document metadata. "CRUD" here means list, read (download) and delete;
  an uploaded file's metadata is a record of what happened and is not editable.
- Restoring a deleted document. Deletion is the product (`VALUE.md` §2).
- Showing deletion history / tombstones — depends on open question **Q-1**.
- Authentication — an explicit non-goal.

## Notes

AC-5 is the criterion a reviewer should check first. A controller that calls
`$document->delete()` directly would pass every visible behaviour in this ticket
and silently break the brief's explicit requirement that manual deletion also
notifies.
