# Mode: REVIEW

Judge a change against what this project promised, before it is committed.

## Load

1. The diff — `git diff`, or `git diff --staged`
2. The ticket it claims to implement
3. [`../context/conventions.md`](../context/conventions.md) — the DON'T list and the Definition of Done
4. [`../context/ontology/README.md`](../context/ontology/README.md) — invariants
5. [`../business/VALUE.md`](../business/VALUE.md) — only if scope looks wrong

## Checklist

**Correctness**
- Does the change satisfy every acceptance criterion, or only most of them?
- Which invariants (I-1…I-10) does it touch? Is each still true?
- Failure modes: broker down, disk full, purge fails, concurrent sweep and manual
  delete of the same document. What happens?
- Is anything published inside a transaction (I-4)? Deleting outside
  `DeleteDocument` (I-3)?

**Scope**
- Does it do what the ticket said and nothing else? Unticketed extras are a
  finding even when the code is good — they were not reviewed against a criterion.
- Does it quietly cross a non-goal from `VALUE.md`?

**Tests**
- Does a test exist for the new behaviour *and* its failure mode?
- Would the test fail if the behaviour regressed? If deleting the implementation
  leaves the test green, the test is decorative.
- Do T-6 and T-7 still pass — the manual and automatic notification paths?

**Conventions**
- `strict_types`, typed signatures, `final`, constructor promotion.
- `env()` only inside `config/*.php`.
- No `dd()`, no commented-out code, no unescaped user data in Blade.
- Names match the ontology.

**Documentation**
- New concept in the ontology? New decision as an ADR? Drifted context module fixed?

## Report

Findings ordered by severity, each with:

- **What** is wrong — one sentence.
- **Where** — `file.php:line`.
- **Why it matters** — the concrete failure, not "this is bad practice". If you
  cannot describe an input that produces a wrong result, it is a preference, and
  should be labelled as one.

Say plainly when a change is good. A review that manufactures findings to look
thorough trains everyone to ignore reviews.

## Rules

- **Review the diff, not the file.** Pre-existing problems are separate tickets.
- **Verify claims.** If the ticket says tests pass, run them.
- **Do not fix while reviewing.** Report; fixing is a separate pass with its own diff.
- **Separate blocking findings from suggestions**, explicitly.
