# TASK-003 — Async upload endpoint with server-side policy

| Field | Value |
| :--- | :--- |
| **Status** | `planned` |
| **Priority** | P1 |
| **Value driver** | V-4 (low friction), V-1 (nothing unwanted is retained) |
| **Depends on** | TASK-002 |
| **Created** | 2026-09-10 |

## Goal

An HTTP endpoint that accepts one PDF or DOCX, enforces the upload policy on the
server, stores the bytes, and returns the created document as JSON — the backend
half of the async uploader.

## Ontology Touchpoints

**Concepts:** `tds:UploadSession`, `tds:UploadPolicy`, `tds:Document`, `tds:StoredObject`
**Invariants:** I-1 (server-side validation is authoritative), I-2 (deadline via
`RetentionPolicy`), I-7 (`originalName` never a path segment), I-8 (MIME from content)
**New concepts:** none

## Acceptance Criteria

- [ ] AC-1 — `POST /documents` accepts `multipart/form-data` with one `file`
      field and returns `201` with `{uuid, original_name, size_bytes, mime_type,
      uploaded_at, expires_at}`.
- [ ] AC-2 — `App\Domain\Upload\UploadPolicy` is built from `config/uploads.php`
      and exposes the max size, allowed MIME types and allowed extensions.
- [ ] AC-3 — An upload over `UPLOAD_MAX_SIZE_BYTES` is refused with `422` and
      `{"code":"too_large"}`.
- [ ] AC-4 — A file whose **detected** MIME type is not whitelisted is refused
      with `{"code":"unsupported_type"}` — including a non-PDF renamed to `.pdf`
      (I-8). Detection uses fileinfo on the temp file, never the client header.
- [ ] AC-5 — A refused upload leaves **no bytes** on the storage disk and creates
      no `Document` row (S-4).
- [ ] AC-6 — Accepted files are stored under a generated `stored_name`; a
      filename such as `../../etc/passwd.pdf` cannot escape the disk (I-7).
- [ ] AC-7 — `expires_at` comes from `RetentionPolicy`, never computed inline (I-2).
- [ ] AC-8 — `checksum_sha256` is recorded.
- [ ] AC-9 — Validation lives in `UploadDocumentRequest`; the controller calls
      `App\Services\UploadDocument` and returns its result — no domain logic in
      the controller.
- [ ] AC-10 — Feature tests cover T-1, T-2, T-3, T-4 and T-15 from
      [`../context/testing.md`](../context/testing.md).

## Out of Scope

- The browser UI and progress bar — `TASK-004`.
- Listing and download — `TASK-005`.
- Deletion of any kind — `TASK-006`.
- Deduplicating identical uploads (open question **Q-2**) — not now.

## Notes

`UploadSession` is not persisted. It exists for the lifetime of one request, as
`UploadDocumentRequest` + `UploadDocument`, per the concept→code map in
[`../context/architecture.md`](../context/architecture.md). Its `rejectionReason`
becomes the `code` field in the 422 body, which is why the codes are fixed
strings rather than translated messages — the UI translates, the API does not.

Note the ordering: validate, then store. Storing first and cleaning up on failure
is the version that leaves orphaned bytes when the cleanup itself fails, and AC-5
exists to rule it out.
