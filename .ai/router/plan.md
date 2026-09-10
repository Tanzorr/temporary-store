# Mode: PLAN

Turn an intent into a ticket that someone else could implement without asking you
a question.

## Load

1. [`../business/VALUE.md`](../business/VALUE.md) — which value driver does this serve?
2. [`../context/ontology/README.md`](../context/ontology/README.md) — which concepts and invariants does it touch?
3. [`../context/architecture.md`](../context/architecture.md) — where would it live?
4. [`../backlog/INDEX.md`](../backlog/INDEX.md) — does a ticket already cover it? Does one block it?

Do **not** load conventions or testing detail yet. Planning at implementation
altitude produces tickets that prescribe code instead of outcomes.

## Produce

A ticket file `../backlog/TASK-NNN-<slug>.md` following
[`../backlog/TEMPLATE.md`](../backlog/TEMPLATE.md), containing:

- **Value trace** — the `V-n` driver. No driver means the ticket does not belong
  in this project; say so instead of writing it.
- **Ontology touchpoints** — concepts involved, invariants that must survive.
- **Acceptance criteria** — observable, checkable statements. "Works correctly"
  is not one. "A manual delete produces exactly one `DeletionEvent` with trigger
  `manual_deletion`" is.
- **Out of scope** — what this ticket explicitly does not do.
- **Dependencies** — tickets that must land first.

## Rules

- **One ticket, one outcome.** If acceptance criteria split cleanly into two
  independent groups, that is two tickets.
- **Prefer thin vertical slices.** A slice that touches UI, service and storage
  and is demonstrable beats three horizontal tickets that individually prove
  nothing.
- **Do not design in the ticket.** Name the outcome and the constraints. Class
  names and method signatures belong to implement mode, which has more information.
- **New concept ⇒ ontology change.** If a ticket introduces something the
  ontology does not contain, updating `index.ttl` is part of that ticket, stated
  in its scope.
- **Unknowns become open questions**, recorded in the ticket or promoted to
  `ontology/README.md` §5. Do not resolve an ambiguity by silently picking one
  reading — say which reading you took and why.

## Done When

The ticket states what to build, why it matters, how to know it works, and what
not to touch — and `INDEX.md` lists it with a status and a priority.
