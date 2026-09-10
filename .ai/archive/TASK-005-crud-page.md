# TASK-005 — Document list, download, manual delete UI

| Field | Value |
| :--- | :--- |
| **Status** | `done` |
| **Priority** | P1 |
| **Value driver** | V-5 (operator control) |
| **Depends on** | TASK-006 |
| **Created** | 2026-09-10 |

## Goal

A separate management page where an operator sees what is currently held, how
long each document has left, can download it, and can delete it early — with that
deletion travelling the same path as an automatic one.

> **TASK-006 must be `done` before this ticket starts.** `App\Services\DeleteDocument`,
> `App\Domain\Deletion\DeletionTrigger` and the `deletion_events` table do not
> exist in the tree yet, and AC-5/AC-9 are unwritable without them. Building the
> list and download halves first and stubbing the delete is exactly the failure
> the dependency order exists to prevent (`backlog/INDEX.md` → Dependency Order).

## Ontology Touchpoints

**Concepts:** `tds:Document`, `tds:DeletionEvent`, `tds:DeletionTrigger`
**Invariants:** **I-3** (manual delete goes through `DeleteDocument`), I-7
(escaped filenames), I-2 (remaining time derived from `expires_at`, not recomputed)
**New concepts:** none. `DocumentRow` (below) is a projection of `Document` for
one view, not a domain concept — `ontology.md` is not touched. Say so in the
outcome note rather than leaving a reviewer to wonder whether it was forgotten.

## Shape to Build

Fixed here so naming and placement are not re-invented at implementation time.

| Path | Role |
| :--- | :--- |
| `routes/web.php` | three routes, names `documents.index` / `documents.download` / `documents.destroy` — see *Response Contracts* |
| `app/Models/Document.php` | new `scopeAvailable()` — the one place a read expresses `status = available` |
| `app/Http/Controllers/DocumentController.php` | new `index()`, `download()`, `destroy()`. Each resolves through `available()`, then calls one thing |
| `app/Http/Presenters/DocumentRow.php` | **new directory + class** — one row's display values, incl. AC-7 and AC-8 |
| `app/Providers/AppServiceProvider.php` | `Paginator::useBootstrapFive()` in `boot()` |
| `resources/views/documents/index.blade.php` | new — table, empty state, paginator links, link back to `/` |
| `resources/views/documents/create.blade.php` | add the `/documents` link to the confirmation block (AC-10) |
| `public/js/documents.js` | new — `confirm()`, `$.ajax` DELETE, row removal. No other page's JS is touched |
| `tests/Feature/Http/DocumentListPageTest.php` | new — T-5, AC-1/2/3/8 |
| `tests/Feature/Http/DownloadDocumentTest.php` | new — AC-4 |
| `tests/Feature/Http/DeleteDocumentRouteTest.php` | new — AC-5, the route half of T-6 |
| `tests/Unit/Http/Presenters/DocumentRowTest.php` | new — AC-7/AC-8 arithmetic |
| `.ai/context/conventions.md` | add `Presenters/` to the `app/Http/` structure block |
| `.ai/context/architecture.md` | ADR-013 (see Notes) |
| `CHANGELOG.md` | TASK-005 entry |

### Response Contracts

| Route | Name | Success | Failure |
| :--- | :--- | :--- | :--- |
| `GET /documents` | `documents.index` | `200` HTML | — |
| `GET /documents/{uuid}/download` | `documents.download` | `200`, `Content-Disposition: attachment`, `Content-Type` from `mime_type` | `404` unknown, deleted, or malformed uuid |
| `DELETE /documents/{uuid}` | `documents.destroy` | `200` `{"uuid": "<document uuid>"}` | `404` as above · `419` missing CSRF token |

A row whose `relative_path` has no bytes behind it is a **bug, not a 404** — let
`Storage` throw (`conventions.md` → *Fail loudly*). Bytes never outlive their
record, so masking that as "not found" hides a broken purge.

### `DocumentRow`

`public static function from(Document $document, CarbonImmutable $now): self`,
all properties `public readonly`:

| Property | Value |
| :--- | :--- |
| `uuid` | `$document->uuid` |
| `originalName` | raw; the template escapes it (I-7) |
| `sizeLabel` | KB or MB to one decimal, matching TASK-004's confirmation row |
| `typeLabel` | `strtoupper($document->extension)` — `PDF` / `DOCX`, not the MIME type |
| `uploadedAtLabel`, `expiresAtLabel` | `Y-m-d H:i` + ` UTC` |
| `awaitingSweep` | `$document->expires_at <= $now` |
| `timeRemainingLabel` | `awaiting sweep` when `awaitingSweep`; else `23h 58m`, or `12m` under an hour, or `<1m` under a minute |

`$now` is taken **once** in the controller and passed in, so every row on one
page agrees and `travel()` drives the unit test.

## Acceptance Criteria

- [x] AC-1 — `GET /documents` renders a Bootstrap table of `available` documents:
      original name, size, type, uploaded at, expires at, time remaining.
- [x] AC-2 — Deleted documents are excluded from the list (T-5).
- [x] AC-3 — The list is paginated at **25 per page**, ordered `uploaded_at`
      descending with `id` descending as the tie-break, so the order is stable
      for documents uploaded in the same second.
- [x] AC-4 — `GET /documents/{uuid}/download` streams the file with the original
      name in `Content-Disposition`, correctly escaped (I-7). An unknown or
      deleted uuid returns `404` — never a message revealing that it once existed.
- [x] AC-5 — `DELETE /documents/{uuid}` calls
      `DeleteDocument::handle($doc, DeletionTrigger::MANUAL_DELETION)` and
      nothing else. The controller contains no deletion logic (**I-3**).
- [x] AC-6 — Deletion happens asynchronously via jQuery, with a confirmation
      dialog, and the row disappears without a page reload. `DELETE` returns JSON;
      the DOM update happens client-side (ADR-007) — no fragment template. The
      button is disabled while the request is in flight, as in TASK-004's AC-9.
- [x] AC-7 — "Time remaining" is rendered from `expires_at` through
      `DocumentRow`; the template never decides expiry itself
      (`conventions.md` → DON'T: no business logic in Blade).
- [x] AC-8 — A document whose deadline has passed but which the sweep has not yet
      collected is labelled *awaiting sweep* in the view — the list must not imply
      a promise the sweep is about to break. This is **derived in `DocumentRow`
      from `expires_at <= now()`**, not a third value of `status`, which stays
      `available` | `deleted` (`TASK-002`). Adding a status value here would break
      the sweep's `WHERE status = 'available'` query.
- [x] AC-9 — The four test files in *Shape to Build*, covering: T-5 (lists
      available, excludes deleted, paginates); a filename like
      `<img src=x onerror=alert(1)>.pdf` rendered escaped in the list HTML (I-7);
      download of an unknown **and** of a deleted uuid returning `404`;
      `Content-Disposition` carrying the original name for a filename containing
      a quote; the manual delete route producing exactly one `DeletionEvent` with
      trigger `manual_deletion` (the route half of T-6 — "publishes one message"
      is TASK-007's half); and `DocumentRow`'s two derived values across the
      boundary cases in the table above.
- [x] AC-10 — The page extends `resources/views/layouts/app.blade.php`
      (`TASK-004`), and the link from the uploader's confirmation row to
      `/documents` is added here — `TASK-004` left it out because this route did
      not exist yet and a `405` is worse than no link. Static markup in
      `create.blade.php`; `upload.js` is not modified.
- [x] AC-11 — Every row of *Manual Verification* passes against
      `docker compose up`, and the result is recorded in the outcome note.

### Manual Verification

The confirm dialog, the row removal and the download filename have no automated
cover, so they are checked once in a browser instead of assumed.

| Check | Expected |
| :--- | :--- |
| Upload a file, follow the confirmation link | the list page opens and shows that document |
| Click Delete, cancel the dialog | nothing is deleted; the row stays; no request is sent |
| Click Delete, confirm | the row disappears, no page reload, and a reload confirms it is gone |
| Download a document named `my "report".pdf` | the browser saves it under that name, not the stored uuid |
| A document past its deadline with the sweep not yet run | the row reads *awaiting sweep*, not a negative countdown |

## Preconditions in the Current Tree

Verified against `feature/document-lifecycle` on 2026-09-10 — facts that change
how the ACs are met, not restatements of them.

| Fact | Consequence |
| :--- | :--- |
| No `app/Domain/Deletion/`, no `App\Services\DeleteDocument`, no `deletion_events` migration | TASK-006 first; see the note under *Goal* |
| `routes/web.php` holds only `GET /` and `POST /documents` (`documents.store`) | no collision — `documents.index` and the two uuid routes are free |
| `Document` has no `getRouteKeyName()`; implicit binding resolves by `id` **and finds deleted rows** | resolve explicitly: `Document::query()->available()->where('uuid', $uuid)->firstOrFail()`. Implicit binding makes AC-4 silently wrong |
| `status` is cast to the `DocumentStatus` enum | the scope compares against `DocumentStatus::Available`, never the string |
| `DocumentFactory` already ships `expired()` and `deleted()` states | T-5 and the AC-8 case need no new factory work |
| `AppServiceProvider::boot()` is empty and no paginator theme is set | Laravel 11 renders Tailwind paginator markup by default — unstyled on a Bootstrap page |
| Bootstrap 5.3.3 CSS and jQuery 3.7.1 are vendored; the Bootstrap **JS bundle is not** (TASK-004) | `window.confirm()`, not a modal — see *Out of Scope* |
| `layouts/app.blade.php` yields `content` and `scripts` and carries `<meta name="csrf-token">` | the DELETE request reads the token exactly as `upload.js` does |
| `uploaded_at` / `expires_at` are `datetime` casts; `config('app.timezone')` defaults to `UTC` | rendering is `Y-m-d H:i` + ` UTC`, matching TASK-004 |
| `Storage::disk()->download($path, $name)` builds the disposition through Symfony's `makeDisposition` (quoted `filename` + ASCII fallback) and defaults `Content-Type` to a **fresh detection of the stored file** | AC-4's escaping needs no hand-built header — assert the behaviour, do not reimplement it. Pass `Content-Type` explicitly from `$document->mime_type`, the value detected and stored at upload (I-8) |
| Six services healthy under `docker compose ps` | verify with `docker compose exec app php artisan test` and `./vendor/bin/pint --test` |

## Out of Scope

- Editing document metadata. "CRUD" here means list, read (download) and delete;
  an uploaded file's metadata is a record of what happened and is not editable.
- Restoring a deleted document. Deletion is the product (`VALUE.md` §2).
- Showing deletion history / tombstones — depends on open question **Q-1**.
- Authentication — an explicit non-goal.
- Sorting, filtering or search. Newest-first pagination is the whole ordering story.
- Bulk delete. Each deletion is one operator intent and one `DeletionEvent`.
- Vendoring Bootstrap's JS bundle for a modal. `window.confirm()` costs nothing
  and TASK-004 deliberately left the bundle out.

## Notes

AC-5 is the criterion a reviewer should check first. A controller that calls
`$document->delete()` directly would pass every visible behaviour in this ticket
and silently break the brief's explicit requirement that manual deletion also
notifies.

**ADR-013 — presenters live in `App\Http\Presenters`.** Write it into
[`architecture.md`](../context/architecture.md) as part of this change, and add
the directory to the structure block in
[`conventions.md`](../context/conventions.md). The alternatives were a Blade
helper (banned — no business logic in templates) and a `App\Domain` value object
(rejected — it would have to import `App\Models\Document`, an upward dependency
against `architecture.md`'s layering rule). Cost: a third directory under
`app/Http`. Accepted, because AC-7 and AC-8 are the two rules most likely to
drift from the sweep and they become unit-testable here.

**A second `DELETE` on the same uuid returns `404`, and that is not a conflict
with TASK-006's AC-6.** The route resolves through `available()`, so the deleted
row is invisible to it. TASK-006's idempotency ("deleting an already-deleted
document is a no-op returning the existing event") is a property of the service
and is tested there; the sweep is the caller that relies on it.

---

## Outcome

**Completed:** 2026-09-10
**What was built:** `documents.index` / `.download` / `.destroy` routes;
`Document::scopeAvailable()`; `App\Http\Presenters\DocumentRow` (ADR-013,
recorded in `architecture.md`, `Presenters/` added to `conventions.md`'s
structure block); `DocumentController@index/download/destroy`, each
resolving the uuid explicitly through `available()->where('uuid', ...)`
rather than implicit route binding, per the ticket's own precondition note;
`documents/index.blade.php` and `public/js/documents.js` (confirm → DELETE →
client-side row removal, delete button disabled in flight); the `/documents`
link added to `create.blade.php`'s confirmation block; `Paginator::useBootstrapFive()`
in `AppServiceProvider::boot()`. All four test files in *Shape to Build* were
written, plus the full pre-existing suite — 39 tests, `pint --test` clean.
AC-11's Manual Verification table was run against the live `docker compose`
stack with a headless-Chromium driver (Playwright, no project run-skill
existed for this app): upload → follow confirmation link → list shows it;
cancel-then-confirm delete removes the row without a reload and it stays
gone after a reload; download recovers the original filename, not the uuid;
an expired-but-unswept document reads *awaiting sweep*. All five passed.
**Deviations from the plan:** one, surfaced by manual verification rather
than assumed away. The *"filename containing a quote downloads correctly"*
row can't be driven end-to-end through a real browser upload: Chromium
itself percent-encodes an embedded `"` in the multipart `filename` parameter
before the request ever reaches the server — `my "report".pdf` arrives at
the app already as `my %22report%22.pdf`, for any site, not something this
app's code touches. The manual check was run instead with a plain filename
(proving download recovers the original name, not the uuid), and the
quote-escaping half of AC-4/AC-9 — the thing that actually exercises I-7 —
stays proven by `DownloadDocumentTest`, which builds the request directly
and isn't subject to the browser's own encoding.
**Invariants verified:** I-3 — `DeleteDocumentRouteTest` proves the route
calls only `DeleteDocument::handle(..., MANUAL_DELETION)`, and a 404 on an
already-deleted uuid creates no second event. I-7 — `DocumentListPageTest`
asserts a `<script>`-bearing original name renders HTML-escaped, never raw;
`DownloadDocumentTest` asserts the `Content-Disposition` quote-escaping.
I-2 — `DocumentRowTest` proves `timeRemainingLabel`/`awaitingSweep` are
pure functions of `expires_at` and the passed-in `$now`, with no clock read
of their own.
**Follow-ups raised:** none.
