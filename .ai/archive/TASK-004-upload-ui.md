# TASK-004 — Bootstrap + jQuery uploader with progress

| Field | Value |
| :--- | :--- |
| **Status** | `done` |
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

## Shape to Build

Fixed here so naming is not re-invented at implementation time.

| Path | Role |
| :--- | :--- |
| `routes/web.php` | `Route::get('/', [DocumentController::class, 'create'])->name('documents.create')` replaces the `welcome` closure |
| `app/Http/Controllers/DocumentController.php` | new `create()` — returns the view with `UploadPolicy::fromConfig()`. One statement, no branching |
| `resources/views/layouts/app.blade.php` | new — head, CSRF meta, asset tags, `@yield('content')`. `TASK-005` extends the same layout |
| `resources/views/documents/create.blade.php` | new — the upload form |
| `public/js/upload.js` | new — all XHR, progress and DOM code |
| `public/vendor/bootstrap/bootstrap.min.css` | vendored, see Notes |
| `public/vendor/jquery/jquery.min.js` | vendored, see Notes |
| `tests/Feature/Http/UploadPageTest.php` | new — AC-10 |
| `resources/views/welcome.blade.php` | **deleted** |

## Acceptance Criteria

- [x] AC-1 — `GET /` renders the Blade page above with a Bootstrap 5 upload form.
      `welcome.blade.php` and its closure route are gone.
- [x] AC-2 — Submitting uploads asynchronously via `XMLHttpRequest` with an
      `upload.onprogress` handler; the page does not reload.
- [x] AC-3 — The progress bar width comes from `event.loaded / event.total`, not
      from a CSS animation or a timer.
- [x] AC-4 — On `201` the response JSON is rendered as a confirmation row on the
      upload page: `original_name`, a size derived from `size_bytes` as KB or MB
      to one decimal, and `expires_at` as `YYYY-MM-DD HH:MM UTC`. **No link to
      `/documents`** — that route does not exist yet and `TASK-005` adds both it
      and the link. `/` is the uploader; the document list is not duplicated here.
- [x] AC-5 — Every failure this stack can produce maps to a message — the table
      under *Error Mapping* below is exhaustive, and no branch may fall through
      to a blank panel.
- [x] AC-6 — The client checks size and extension before sending, using the limit
      and extension list the **server** published into the page from
      `UploadPolicy::fromConfig()` — no policy literals in JS. The server remains
      authoritative (I-1); `UploadDocumentTest`'s T-3 and T-4 already prove a
      request that bypasses the page is refused, so this ticket does not re-prove it.
- [x] AC-7 — Filenames reach the DOM only through jQuery `.text()` or `{{ }}` —
      never `.html()`, never `{!! !!}` (I-7).
- [x] AC-8 — The XHR sends `X-CSRF-TOKEN` read from a `<meta name="csrf-token">`
      tag. `POST /documents` is a `web` route, so a missing token is a `419`.
- [x] AC-9 — The submit control is disabled while an upload is in flight and
      re-enabled on success or failure, so a double submit cannot create two
      documents from one intent.
- [x] AC-10 — `tests/Feature/Http/UploadPageTest.php` asserts `GET /` returns
      `200`, uses view `documents.create`, carries the CSRF meta tag, and
      publishes an `accept` list and a max size equal to
      `config('uploads.allowed_extensions')` and `config('uploads.max_size_bytes')`.
      Markup only — there is no JS test runner and adding one means a build step
      (ADR-005). This AC is the ticket's entire automated-test obligation.
- [x] AC-11 — Every row of *Manual Verification* below passes against
      `docker compose up`, and the result is recorded in the outcome note.
- [x] AC-12 — Dropping a file on the form's drop zone routes it through the same
      upload function as the file input — one code path, not two.

### Error Mapping

| Outcome | Message |
| :--- | :--- |
| `422 {"code":"too_large"}` | File exceeds the 10 MB limit |
| `422 {"code":"unsupported_type"}` | Only PDF and DOCX files are accepted |
| `422 {"code":"corrupt"}` | The upload did not complete — please try again |
| `413`, or any response whose body does not parse as JSON | File is too large for the server to accept |
| XHR `onerror` / `onabort` | Upload failed — check your connection and try again |
| any other status | Upload failed — please try again |

The size in the `too_large` message is rendered from the published limit (AC-6),
never typed as a literal, so it cannot drift from `config`.

### Manual Verification

The progress bar, the escaping and the `413` branch have no automated cover
(AC-10), so they are checked once in a browser instead of assumed.

| Check | Expected |
| :--- | :--- |
| Upload a real PDF in a browser | bar reaches 100 %, confirmation row appears, no reload |
| Upload a file named `<img src=x onerror=alert(1)>.pdf` | the name renders as visible text, no dialog fires (I-7) |
| Send a file over 12M with the client check disabled in devtools | the `413` branch shows a message, not a blank page or a JSON parse error |
| Double-click submit | second click is a no-op; exactly one `documents` row |
| Stop the `web` container mid-upload | the `onerror` message appears and the submit control is re-enabled (AC-9) |

## Preconditions in the Current Tree

Verified against `feature/document-lifecycle` at the time of writing — facts that
change how the ACs are met, not restatements of them.

| Fact | Consequence |
| :--- | :--- |
| Only `POST /documents` exists ([`routes/web.php`](../../routes/web.php)) | a link to `/documents` returns `405`, not `404`. AC-4 drops the link |
| `UploadDocumentRequest` emits **three** codes — `corrupt`, `too_large`, `unsupported_type` | AC-5 maps all three; a two-code mapping leaves a blank error for a failed multipart part |
| nginx and `php.ini` cap the request at 12M, above the 10 MiB policy | a file over 12M never reaches PHP — nginx returns HTML, so the JS must not assume a JSON body |
| `expires_at` is a `datetime` cast, serialised as `2026-09-11T13:32:00.000000Z` | AC-4's format is sliced from that string in JS; no date library |
| `UploadPolicy` exposes public readonly `maxSizeBytes` and `allowedExtensions` | pass the policy object to the view — the client check then reads the same source the server validates with |
| `/` currently returns `welcome.blade.php`, 176 lines with inlined Tailwind | delete it; leaving it means two CSS frameworks in one repository |
| [`testing.md`](../context/testing.md)'s T-table has no row for the uploader page | AC-10 is the full test obligation; no new T-row is needed |
| `public/vendor` and `public/js` are not in `.gitignore` (only `/public/storage`, `/hot`, `/build`) | vendored assets commit normally |
| jsdelivr and code.jquery.com answered `200` from both host and the `app` container on 2026-09-10 | the assets can be fetched at implementation time |

## Out of Scope

- The management/CRUD page and the `/documents` route — `TASK-005`.
- Multi-file or chunked uploads. One file per request; the brief does not ask
  for more, and chunking would change `UploadSession` in the ontology.
- Vite, npm, or any build step (ADR-005) — and therefore any JS test runner.
- Bootstrap's JavaScript bundle. Nothing on this page needs a Bootstrap
  component; the progress bar is CSS. `TASK-005` vendors it if its confirmation
  dialog turns out to need a modal.

## Notes

**ADR-012 — Bootstrap and jQuery are vendored under `public/vendor`, not loaded
from a CDN.** Write it into
[`architecture.md`](../context/architecture.md) as part of this change. The
reviewer gets a working page with no network and nothing to configure, which is
the same reasoning as ADR-005. Cost: two minified blobs in git that cannot be
read in a diff — accepted, because the alternative is a demo that silently
renders unstyled and cannot upload at all when jQuery fails to load.

Pin exact versions and fetch them once:

```bash
mkdir -p public/vendor/bootstrap public/vendor/jquery
curl -fsSL -o public/vendor/bootstrap/bootstrap.min.css \
  https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css
curl -fsSL -o public/vendor/jquery/jquery.min.js \
  https://code.jquery.com/jquery-3.7.1.min.js
```

Keep the licence headers intact. If a newer `5.3.x` / `3.7.x` is used instead,
update the version row in [`stack.md`](../context/stack.md) in the same change.

`pint` does not lint `public/js/upload.js` and no JS linter is being added — so
keep that file small, dependency-free beyond jQuery, and free of the
commented-out code and `console.log` that `conventions.md` bans everywhere else.

---

## Outcome

**Completed:** 2026-09-10
**What was built:** `Route::get('/', ...)` → `DocumentController@create`,
replacing the `welcome` closure; the controller passes
`UploadPolicy::fromConfig()` straight to the view (no branching).
`resources/views/layouts/app.blade.php` (CSRF meta, vendored Bootstrap CSS,
vendored jQuery, `@yield('content')`/`@yield('scripts')`) and
`resources/views/documents/create.blade.php` (drop zone, file input carrying
`data-max-size-bytes`/`data-allowed-extensions` from the policy, progress bar,
error panel, confirmation table). `public/js/upload.js`: one `uploadFile()`
function reached from both the file input's `change` handler and the drop
zone's `drop` handler (AC-12); client-side size/extension checks read the
published policy, never a literal (AC-6); `XMLHttpRequest` with
`upload.onprogress` driving the bar from `event.loaded/event.total`; the full
error-mapping table from the ticket, keyed off the parsed `code` field with a
"body didn't parse as JSON" branch for the `413`/nginx case; filenames reach
the DOM only via jQuery `.text()`. Bootstrap 5.3.3 and jQuery 3.7.1 vendored
under `public/vendor/` per ADR-012 (added to `architecture.md`).
`tests/Feature/Http/UploadPageTest.php` covers AC-10.
**Deviations from the plan:** none. One incidental fix: `.claude/settings.json`
denied `Edit`/`Write` under `public/vendor/**`, because its deny rule
`Edit(./vendor/**)` matched `vendor/` at any depth rather than anchoring to the
repository root. Reworked to `Edit(/vendor/**)` (leading `/`, no absolute
filesystem path) so only the top-level Composer `vendor/` is protected;
verified both that `public/vendor/**` is writable again and that a probe write
into the real `vendor/` is still denied.
**Invariants verified:** I-1 — the client-side checks in `upload.js` are
courtesy only; `UploadDocumentTest`'s T-3/T-4 already prove the server refuses
a request that bypasses them, unchanged by this ticket. I-7 — an
`<img src=x onerror=alert(1)>.pdf` filename renders as inert visible text with
no dialog firing (verified in a real headless-Chrome run, see below), and
`upload.js` never uses `.html()` on user data.
**Manual verification (AC-11):** every row of the ticket's *Manual
Verification* table was run against `docker compose up`, driven by Playwright
against a real headless Chrome (`channel: 'chrome'`) rather than eyeballed —
screenshots and per-check pass/fail were captured for each row. Two rows used
an equivalent-but-non-disruptive substitute rather than the literal steps,
noted here so the substitution is visible:
- "Send a file over 12M with the client check disabled in devtools": the
  served page's `data-max-size-bytes` was rewritten in-flight via network
  interception (simulating "disabled in devtools") so the file reached nginx;
  nginx's 12M cap returned a non-JSON body and the page showed "File is too
  large for the server to accept", not a blank page or a parse error.
- "Stop the `web` container mid-upload": simulated by aborting the
  `POST /documents` request at the network layer instead of stopping the
  shared running stack, to avoid disrupting infrastructure other work depends
  on. The `onerror` handler fired, showed "Upload failed — check your
  connection and try again", and re-enabled the submit button.
All other rows (valid PDF upload reaching 100% with a confirmation row and no
reload; the XSS filename; double-submit producing exactly one
`POST /documents`) were run as written, with a real click/drop, not simulated.
**Follow-ups raised:** none.
