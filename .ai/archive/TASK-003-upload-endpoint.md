# TASK-003 — Async upload endpoint with server-side policy

| Field | Value |
| :--- | :--- |
| **Status** | `done` |
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

- [x] AC-1 — `POST /documents` accepts `multipart/form-data` with one `file`
      field and returns `201` with `{uuid, original_name, size_bytes, mime_type,
      uploaded_at, expires_at}`.
- [x] AC-2 — `App\Domain\Upload\UploadPolicy` is built from `config/uploads.php`
      and exposes the max size, allowed MIME types and allowed extensions.
- [x] AC-3 — An upload over `UPLOAD_MAX_SIZE_BYTES` is refused with `422` and
      `{"code":"too_large"}`.
- [x] AC-4 — A file whose **detected** MIME type is not whitelisted is refused
      with `{"code":"unsupported_type"}` — including a non-PDF renamed to `.pdf`
      (I-8). Detection uses fileinfo on the temp file, never the client header.
- [x] AC-5 — A refused upload leaves **no bytes** on the storage disk and creates
      no `Document` row (S-4).
- [x] AC-6 — Accepted files are stored under a generated `stored_name`; a
      filename such as `../../etc/passwd.pdf` cannot escape the disk (I-7).
- [x] AC-7 — `expires_at` comes from `RetentionPolicy`, never computed inline (I-2).
- [x] AC-8 — `checksum_sha256` is recorded.
- [x] AC-9 — Validation lives in `UploadDocumentRequest`; the controller calls
      `App\Services\UploadDocument` and returns its result — no domain logic in
      the controller.
- [x] AC-10 — Feature tests cover T-1, T-2, T-3, T-4 and T-15 from
      [`../context/testing.md`](../context/testing.md).

## Preconditions in the Current Tree

Verified against `main` at the time of writing — facts that change how the ACs are
met, not restatements of them.

| Fact | Consequence |
| :--- | :--- |
| `config/uploads.php` has only `max_size_bytes` and `disk` | AC-2 needs `allowed_mime_types` and `allowed_extensions` added there as fixed arrays — PDF/DOCX is a product rule, not deployment config, so no `env()` |
| There is no `routes/api.php` | `POST /documents` goes in `routes/web.php` and CSRF applies; `TASK-004`'s XHR must send `X-CSRF-TOKEN` |
| `UploadedFile::fake()->create()` writes empty content | fileinfo detects it as `application/x-empty`, so AC-4 refuses it. T-1/T-2 need real fixture bytes under `tests/Fixtures/` |
| fileinfo may report a real DOCX as `application/zip` | The whitelist must state how DOCX is admitted. If the rule is anything beyond a MIME match, it is a decision → ADR |
| `docker/php/php.ini` and `docker/nginx/default.conf` cap requests at 12M | AC-3's oversized case must sit between `UPLOAD_MAX_SIZE_BYTES` (10 MiB) and 12M, or the stack returns `413` before `UploadPolicy` is reached |

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

---

## Outcome

**Completed:** 2026-09-10
**What was built:** `App\Domain\Upload\UploadPolicy` (built from
`config/uploads.php`'s new `allowed_mime_types`/`allowed_extensions` — parallel,
fixed arrays, no `env()`); `App\Domain\Upload\MimeTypeDetector`, a one-method
wrapper around `finfo` on the temp path, used by both the request and the
service so content detection exists in exactly one place; `UploadDocumentRequest`
doing all validation in `withValidator()->after()`, producing only the fixed
codes `corrupt` / `too_large` / `unsupported_type`; `App\Services\UploadDocument`
storing accepted files as `{uuid}.{extension}` (extension from the detected MIME
type via `UploadPolicy`, never from the client filename) and creating the
`Document` row with `expires_at` from `RetentionPolicy`; `DocumentController@store`
translating request → service → JSON, no branching; `POST /documents` route in
`routes/web.php` (no `routes/api.php` yet, per the preconditions table above).
Verified against the real stack, not just SQLite feature tests: a `curl` round
trip through `nginx` + `php-fpm` (session cookie + `X-XSRF-TOKEN`) accepted a
real PDF, wrote real bytes under `storage/app/private/documents/`, and rejected
a disallowed-content file with `{"code":"unsupported_type"}`; that manual
artifact was removed afterward.
**Deviations from the plan:** `App\Domain\Upload\MimeTypeDetector` was added —
not named in the ticket, but without it the fileinfo call would exist twice
(request + service), which is the exact Shotgun Surgery smell `conventions.md`
warns about for a two-call-site rule.
**Invariants verified:** I-1 (`UploadDocumentTest` — no `Document` row exists
for a refused upload); I-2 (`expires_at` asserted equal to
`RetentionPolicy::deadlineFor(uploaded_at)`, not a hardcoded offset); I-7 (a
`../../etc/passwd.pdf` original name never reaches `relative_path`, and
Symfony's `UploadedFile::getClientOriginalName()` independently reduces it to a
basename — defense in depth, not reliance on the framework alone); I-8
(rejection is driven by `finfo` on file content — proven by a `.pdf`-named file
whose real content is plain text being refused even though the smoke test sent
`Content-Type: application/pdf`).
**Follow-ups raised:** ADR-011 records that DOCX is whitelisted by its exact
OOXML MIME type, verified empirically against the target `app` image rather
than hedged with an `application/zip` fallback — see
[`../context/architecture.md`](../context/architecture.md). No new backlog
tickets.
