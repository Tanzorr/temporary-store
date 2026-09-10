# TASK-012 — Reject an over-long `originalName` as a policy failure, not a 500

| Field | Value |
| :--- | :--- |
| **Status** | `planned` |
| **Priority** | P2 (quality) |
| **Value driver** | `V-4` |
| **Depends on** | `TASK-011` |
| **Created** | 2026-09-10 |

## Goal

An upload whose filename is longer than the `documents.original_name` column
gets the same `422` + code as any other admission failure, instead of a `500`
from the database.

## Ontology Touchpoints

**Concepts:** `UploadSession`, `UploadPolicy`, `Document`
**Invariants that must hold:** `I-1`, `I-7`
**New concepts introduced:** none — `UploadPolicy` gains a bound, not a concept.

## Acceptance Criteria

- [ ] AC-1 — `UploadPolicy` carries the maximum `originalName` length, read from
      `config/uploads.php`, and it matches the column width.
- [ ] AC-2 — An upload exceeding it returns `422` with a code from the fixed set
      in `conventions.md` (a new one requires updating that list and
      `UploadSession.rejectionReason` in `ontology.md`).
- [ ] AC-3 — A test drives a filename one character over the bound.

## Notes

Found in the TASK-011 review. Since TASK-011 the failure no longer orphans
bytes on disk — it is now only a wrong status code, which is why this is P2.
