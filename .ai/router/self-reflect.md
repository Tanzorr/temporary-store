# Mode: SELF-REFLECT

A checkpoint before declaring work finished, and the honest accounting when
something went wrong.

## Before Reporting Done

Answer each, out loud, in the ticket's outcome note:

1. **Did I do what was asked?** Compare against the acceptance criteria as
   written, not as remembered. Partially satisfying a criterion is not
   satisfying it.
2. **Did I run the checks, or assume them?** If `php artisan test` was not run,
   say that instead of implying it passed.
3. **Which invariants did I touch, and are they still true?** Name them. I-1…I-10.
4. **What did I change that was not in the ticket?** Anything on this list is a
   finding to disclose, not a bonus.
5. **What did I skip?** Say it explicitly and why. A silently dropped criterion
   is the failure mode that costs the most trust.
6. **What is now stale?** Ontology, ADRs, context modules, `INDEX.md` files.
7. **What would a reviewer catch?** If you can name it, fix it or disclose it now.

## When Something Went Wrong

- **State it plainly, once.** "The sweep test fails; output below." No preamble,
  no extended apology — both cost the reader time and neither fixes anything.
- **Show the evidence**, not a paraphrase of it.
- **Say what you know versus what you infer.** These get conflated under pressure
  to sound confident, and the conflation is what makes a report untrustworthy.
- **Correct and continue.** One correction, then back to the work.

## Signals to Stop and Ask

Stop rather than push through when:

- The ticket's criteria contradict the ontology or `VALUE.md`.
- The change requires crossing a stated non-goal.
- A fix demands a new dependency, a schema rewrite, or breaking an invariant.
- The third different attempt at the same failure has not worked — at that point
  the model of the problem is wrong, and more attempts refine the wrong model.

## Anti-Patterns

| Pattern | Why it is corrosive |
| :--- | :--- |
| "Should work now" | An untested claim presented as a result. Run it |
| Reporting done with a criterion unmet | The single fastest way to make every future report worthless |
| Fixing a failing test by weakening its assertion | Removes the guard and keeps the bug |
| Expanding scope because it was convenient | Ships unreviewed code under a reviewed ticket |
| Burying a failure at the end of a long summary | Reads as concealment even when it is not |
| Re-litigating a decision already recorded as an ADR | The decision is made; reopen it as a ticket if it is genuinely wrong |
