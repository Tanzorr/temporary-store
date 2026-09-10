# TASK-009 — Test suite covering T-1…T-15

| Field | Value |
| :--- | :--- |
| **Status** | `planned` |
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

- [ ] AC-1 — Every row T-1…T-15 in `testing.md` has a corresponding test, named
      after the behaviour it proves.
- [ ] AC-2 — Gaps left by earlier tickets are filled — in particular T-9
      (idempotent sweep), T-10 (rollback publishes nothing), T-11 (retry without
      duplication), T-14 (message id = event id).
- [ ] AC-3 — **T-6 and T-7 both pass**, proving a notification for manual *and*
      automatic deletion. These are the project's acceptance tests.
- [ ] AC-4 — At least one integration test runs against a real MySQL and a real
      RabbitMQ, and is skipped cleanly (not failed) when the broker is absent.
- [ ] AC-5 — `php artisan test` passes with no skipped tests in the default suite.
- [ ] AC-6 — `./vendor/bin/pint --test` is clean.
- [ ] AC-7 — Mutation check by hand on the two criteria that matter most: comment
      out the publish call and confirm T-6 and T-7 both fail. A test that stays
      green here is decorative and must be rewritten.
- [ ] AC-8 — No test uses `sleep()`; time is controlled with `Carbon::setTestNow()`
      or `travel()`.
- [ ] AC-9 — Tests are independent — the suite passes with `--order=random`.

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
