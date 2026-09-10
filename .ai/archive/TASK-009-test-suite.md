# TASK-009 — Test suite covering T-1…T-15

| Field | Value |
| :--- | :--- |
| **Status** | `done` |
| **Priority** | P1 |
| **Value driver** | V-3 (the deletion promise is only credible if proven) |
| **Depends on** | TASK-008 |
| **Created** | 2026-09-10 |

## Goal

Close the gap between "the tests each ticket added" and the full coverage table
in [`../context/testing.md`](../context/testing.md), so every invariant has at
least one test that would fail if it broke.

## Ontology Touchpoints

**Concepts:** all
**Invariants:** I-1 … I-10
**New concepts:** none

## Acceptance Criteria

- [x] AC-1 — Every row T-1…T-15 in `testing.md` has a corresponding test, named
      after the behaviour it proves.
- [x] AC-2 — Gaps left by earlier tickets are filled — in particular T-9
      (idempotent sweep), T-10 (rollback publishes nothing), T-11 (retry without
      duplication), T-14 (message id = event id).
- [x] AC-3 — **T-6 and T-7 both pass**, proving a notification for manual *and*
      automatic deletion. These are the project's acceptance tests.
- [x] AC-4 — At least one integration test runs against a real MySQL and a real
      RabbitMQ, and is skipped cleanly (not failed) when the broker is absent.
- [x] AC-5 — `php artisan test` passes with no skipped tests in the default suite.
- [x] AC-6 — `./vendor/bin/pint --test` is clean.
- [x] AC-7 — Mutation check by hand on the two criteria that matter most: comment
      out the publish call and confirm T-6 and T-7 both fail. A test that stays
      green here is decorative and must be rewritten.
- [x] AC-8 — No test uses `sleep()`; time is controlled with `Carbon::setTestNow()`
      or `travel()`.
- [x] AC-9 — Tests are independent — the suite passes with `--order=random`.

## Out of Scope

- Coverage percentage targets. The table is the target; a percentage measures
  lines executed, not invariants proven.
- Browser/E2E tests. Feature tests cover the HTTP layer, and the UI is thin by
  design (ADR-005).
- Load and performance testing.

## Notes

AC-7 is the one that distinguishes a real suite from a green badge. If the tests
still pass after the behaviour is removed, they never tested it — and this
project's whole claim (V-3: deletion is observable) rests on exactly those two
tests. Do it manually, once, and record the result in the outcome note.

---

## Outcome

**Completed:** 2026-09-10
**What was built:** Nothing — every T-1…T-15 row already had a named test from
TASK-003/005/006/007/008. This ticket was verification: ran the full suite,
`pint`, `--order=random`, and the AC-7 mutation check by hand; checked off the
Acceptance Criteria against the existing suite.
**Deviations from the plan:** None. AC-2's named gaps (T-9, T-10, T-11, T-14)
turned out to already be covered — `SweepExpiredDocumentsTest::running_the_sweep_twice_…`,
`PublishDeletionNotificationTest::it_publishes_nothing_when_the_deletion_rolls_back`,
`…it_retries_after_a_publish_failure_without_duplicating_the_event`, and
`DocumentDeletedMessageTest`'s `messageId` assertion respectively.
**Invariants verified:** I-3 by the AC-7 mutation — commented out
`PublishDeletionNotification::dispatch` in `DispatchDeletionNotification`, reran
`--filter=PublishDeletionNotificationTest`: 3 of 5 failed (including the T-6 and
T-7 tests), confirming they'd catch a broken publish path; reverted, reran clean
(5 passed). Full suite: 51 passed, 0 skipped (RabbitMQ was reachable, so the
integration test ran for real rather than skipping). `--order=random` also 51
passed. `pint --test` clean.
**Follow-ups raised:** None.
