# Mode: IMPLEMENT

Build what a ticket describes.

## Load

1. The ticket from [`../backlog/`](../backlog/INDEX.md) — the whole thing, including "out of scope"
2. [`../context/conventions.md`](../context/conventions.md) — every time, no exceptions
3. [`../context/architecture.md`](../context/architecture.md) — layering, concept→code map, ADRs
4. [`../context/stack.md`](../context/stack.md) — versions, services, env keys
5. [`../context/testing.md`](../context/testing.md) — before writing tests
6. The invariants the ticket names, from [`../context/ontology.md`](../context/ontology.md)

## Sequence

1. **Re-read the acceptance criteria.** They define done. If they are ambiguous,
   resolve that now — an assumption discovered at review costs the whole change.
2. **Locate the seams.** Which existing files change? Read them fully before
   editing. Use the concept→code map rather than guessing at paths.
3. **Write the test first for the invariant-bearing behaviour.** Not dogma about
   TDD — it is that I-3-class invariants are exactly what a
   written-afterwards test quietly fails to cover.
4. **Implement the smallest thing that satisfies the criteria.** Not the general
   case. Not the version that also handles the feature nobody asked for.
5. **Run the checks**: `php artisan test`, then `pint`.
6. **Update what the change invalidated** — ontology, ADR, context module.
7. **Close out**: move the ticket to [`../archive/`](../archive/INDEX.md) with an
   outcome note, per the Definition of Done in `conventions.md`.

## Rules

- **Stay inside the ticket.** Something else being broken is a new ticket. Fixing
  it here makes the diff unreviewable and the revert impossible.
- **Never delete a `Document` outside `DeleteDocument`** — invariant I-3, the one
  rule most worth protecting in this codebase.
- **New concept ⇒ update `ontology.md`** in the same change, not later.
- **New decision ⇒ ADR** in `architecture.md`. A choice between two real options,
  recorded in four lines, is worth more than any amount of inline commentary.
- **Blocked or the ticket is wrong?** Stop and say so. Do not implement something
  adjacent and describe it as the ticket.
- **Never claim a passing test you did not run.** Paste the failure if it failed.

## Done When

The Definition of Done in [`../context/conventions.md`](../context/conventions.md)
is satisfied in full — every item, not the convenient five.
