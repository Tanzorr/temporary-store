# TASK-004 — Bootstrap + jQuery uploader with progress

| Field | Value |
| :--- | :--- |
| **Status** | `planned` |
| **Priority** | P1 |
| **Value driver** | V-4 |
| **Depends on** | TASK-003 |
| **Created** | 2026-09-10 |

## Goal

A page where a user picks or drops a PDF/DOCX, watches a real progress bar, and
sees either the created document or a precise reason for refusal — without a page
reload.

## Ontology Touchpoints

**Concepts:** `tds:UploadSession`, `tds:UploadPolicy`
**Invariants:** I-1 (client checks are a courtesy; the server decides), I-7
(`originalName` is escaped when displayed)
**New concepts:** none

## Acceptance Criteria

- [ ] AC-1 — `GET /` renders a Blade page with a Bootstrap 5 upload form.
- [ ] AC-2 — Submitting uploads asynchronously via `XMLHttpRequest` with an
      `upload.onprogress` handler; the page does not reload.
- [ ] AC-3 — A progress bar shows real transferred percentage, not a fake animation.
- [ ] AC-4 — On success the new document appears in the page's list without a reload.
- [ ] AC-5 — On `422` the server's `code` is mapped to a human message
      (`too_large` → "File exceeds the 10 MB limit", `unsupported_type` → "Only
      PDF and DOCX files are accepted").
- [ ] AC-6 — Client-side size and extension checks give immediate feedback, and
      the server still enforces both (I-1). Removing the client check must not
      change what the server accepts — worth verifying once by hand.
- [ ] AC-7 — Filenames are rendered with `{{ }}` / jQuery `.text()`, never
      `{!! !!}` or `.html()` (I-7).
- [ ] AC-8 — CSRF token is sent with the request.
- [ ] AC-9 — The upload control is disabled while an upload is in flight, so a
      double submit cannot create two documents from one intent.

## Out of Scope

- The management/CRUD page — `TASK-005`.
- Multi-file or chunked uploads. One file per request; the brief does not ask
  for more, and chunking would change `UploadSession` in the ontology.
- Drag-and-drop is optional; if it is added it must use the same code path.

## Notes

No build step (ADR-005). Bootstrap and jQuery are included as static assets or
from a CDN pinned to an exact version. Adding Vite here would lengthen the
reviewer's setup for no gain the brief asked for.
