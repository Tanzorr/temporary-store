# TASK-002 — `documents` table, model, factory

| Field | Value |
| :--- | :--- |
| **Status** | `done` |
| **Priority** | P0 |
| **Value driver** | V-3 (a deletion cannot be auditable if the record never existed) |
| **Depends on** | TASK-001 |
| **Created** | 2026-09-10 |

## Goal

Persist the `Document` concept, including the retention deadline, so that upload,
listing, sweeping and deletion all have something concrete to operate on.

## Ontology Touchpoints

**Concepts:** `tds:Document`, `tds:StoredObject`, `tds:RetentionPolicy`
**Invariants:** I-2 (`expiresAt = uploadedAt + ttlHours`, computed in one place)
**New concepts:** none

## Acceptance Criteria

- [ ] AC-1 — Migration creates `documents` with columns matching the ontology's
      datatype properties: `id`, `uuid`, `original_name`, `stored_name`,
      `mime_type`, `extension`, `size_bytes`, `checksum_sha256`, `disk`,
      `relative_path`, `status`, `uploaded_at`, `expires_at`, timestamps.
- [ ] AC-2 — `uuid` is unique and indexed; `expires_at` is indexed (ADR-002 —
      the sweep selects on it).
- [ ] AC-3 — A composite index supports `WHERE status = 'available' AND expires_at <= ?`.
- [ ] AC-4 — `App\Models\Document` casts `uploaded_at` / `expires_at` to
      datetime and `status` to a `DocumentStatus` enum.
- [ ] AC-5 — `App\Domain\Retention\RetentionPolicy` reads `retention.ttl_hours`
      and exposes `deadlineFor(CarbonImmutable $uploadedAt): CarbonImmutable`.
      **No other code computes an expiry** (I-2).
- [ ] AC-6 — A unit test proves `RetentionPolicy` produces `uploadedAt + 24h` by
      default and honours a changed config value.
- [ ] AC-7 — `DocumentFactory` produces valid records, with `expired()` and
      `deleted()` states for later tests.
- [ ] AC-8 — `StoredObject` value object wraps `disk` + `relative_path`; the
      model exposes it rather than leaking two loose strings.

## Out of Scope

- The `deletion_events` table — `TASK-006`.
- Any upload or HTTP code — `TASK-003`.
- Storing files on disk — `TASK-003`.

## Notes

`status` is `available` | `deleted`. A deleted `Document` keeps its row as a
tombstone; whether tombstones are eventually pruned is open question **Q-1** in
[`../context/ontology.md`](../context/ontology.md) and is not
decided by this ticket.

All timestamps are UTC (`conventions.md` → DO). Store `uploaded_at` explicitly
rather than reusing `created_at`: the retention promise is a domain fact, and
should not silently change if a row is ever backfilled or re-created.

`checksum_sha256` has no reader in this backlog — deduplication is open question
**Q-2** and out of scope. It is kept because it is one line at write time and is
what lets an operator verify a downloaded file matches what was stored. If that
justification does not convince at review, drop the column rather than keeping a
field nobody uses.

---

## Outcome

**Completed:** 2026-09-10
**What was built:** `documents` migration (unique-indexed `uuid`, indexed
`expires_at`, composite `(status, expires_at)` index); `App\Models\Document`
casting `status` to `App\Domain\Document\DocumentStatus` and `uploaded_at`/
`expires_at` to `datetime`, exposing `storedObject(): StoredObject`;
`App\Domain\Storage\StoredObject` (readonly `disk` + `relativePath`);
`App\Domain\Retention\RetentionPolicy::deadlineFor()`, the sole reader of
`retention.ttl_hours`; `DocumentFactory` with `expired()`/`deleted()` states,
resolving its default `expires_at` through `RetentionPolicy` rather than
hardcoding 24h a second time. Verified against real MySQL (not just SQLite):
migration applied cleanly with all three indexes present.
**Deviations from the plan:** none — built as specified.
**Invariants verified:** I-2 — `RetentionPolicyTest` proves the default
24h deadline and that changing `retention.ttl_hours` at runtime changes the
computed deadline; `deadlineFor()` is the only place in the codebase that
adds to `uploadedAt`.
**Follow-ups raised:** none.
