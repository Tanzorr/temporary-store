# Testing

Tests exist to prove the invariants in
[`ontology.md`](ontology.md), not to raise a coverage number. A
test that cannot fail proves nothing.

## Layers

| Layer | Directory | Runs against | Use for |
| :--- | :--- | :--- | :--- |
| Unit | `tests/Unit` | Nothing external | Pure rules: retention arithmetic, policy validation, payload shape |
| Feature | `tests/Feature` | SQLite/MySQL + fakes | HTTP endpoints, services, jobs, the console command |
| Integration | `tests/Integration` | Real MySQL + RabbitMQ | The publish path end to end. Skipped when the broker is absent |

Default to **feature tests**. They exercise the wiring, which is where this
system's risk actually lives — the requirement most likely to be missed is
"the second delete path also notifies", and only a feature test catches that.

## Fakes

- `Storage::fake('local')` for uploads and purges.
- `Queue::fake()` to assert a job was dispatched — but at least one test must run
  the job for real, or a broken publisher passes every test.
- Bind a fake `NotificationPublisher` (ADR-004) that records messages in memory.
  Assert on message content, not on "some job was dispatched".
- `travel()` / `Carbon::setTestNow()` for expiry. **Never `sleep()`**.

## Required Coverage

Each row must have at least one test. Missing rows are gaps in the product
promise, not in a metric.

| # | Behaviour | Proves |
| :--- | :--- | :--- |
| T-1 | Valid PDF upload creates a `Document` with correct metadata and `expires_at` | I-1, I-2 |
| T-2 | Valid DOCX upload is accepted | I-1 |
| T-3 | Oversized upload is refused server-side, leaves no bytes on disk | S-4 |
| T-4 | Disallowed type (e.g. `.exe` renamed `.pdf`) is refused by content detection | I-8, S-4 |
| T-5 | CRUD page lists available documents and excludes deleted ones | V-5 |
| T-6 | **Manual delete** creates a `DeletionEvent` with `manual_deletion` and publishes one message | **I-3**, S-3 |
| T-7 | **Sweep** deletes an expired document, creates `retention_expiry`, publishes one message | **I-3**, S-1, S-3 |
| T-8 | Sweep does not touch a document that has not expired | I-2 |
| T-9 | Running the sweep twice deletes nothing twice and publishes nothing twice | I-6 |
| T-10 | A rolled-back deletion publishes nothing | I-4 |
| T-11 | Publish failure retries and eventually succeeds without duplicating the event | I-5, I-10 |
| T-12 | Purge failure aborts the deletion; document stays available | Purge-failure risk |
| T-13 | Payload contains recipient, document metadata, trigger code, schema version | V-6 |
| T-14 | Message id equals the `DeletionEvent` id | I-5 |
| T-15 | A filename like `../../etc/passwd.pdf` does not escape the storage disk | I-7 |

**T-6 and T-7 together are the acceptance test for this project.** They are the
pair that proves the requirement the brief calls out explicitly — notification on
manual deletion, not only automatic.

## Writing Tests

- One behaviour per test. A test asserting five things reports one failure.
- Name the behaviour, not the method: `it_refuses_an_oversized_upload`.
- Arrange with factories; never depend on rows another test created.
- Assert the observable outcome — a row, a response, a recorded message — not
  that a mock was called, unless the call *is* the outcome.
- Cover the failure mode too. A happy-path-only test suite gives false confidence
  in exactly the paths (broker down, purge failed) that carry the real risk.

## Commands

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=DeleteDocument
docker compose exec app php artisan test --testsuite=Feature
docker compose exec app ./vendor/bin/pint --test
```

## Before Claiming Something Works

Run the suite and read the output. "Should pass" is not a result. If a test
fails, report the failure with its output rather than describing the intent.
