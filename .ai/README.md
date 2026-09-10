# `.ai/` — Spec-Driven Development Layer

The knowledge an AI agent (or a new engineer) needs to work on this repository
correctly. It is version-controlled, reviewable, and treated as part of the
product rather than as scratch notes.

## Why

Prompting an agent per-task produces per-task quality: each session re-derives
the domain, invents its own names, and re-decides settled questions — differently
each time. This layer moves that knowledge out of the prompt and into the
repository, where it can be reviewed, corrected once, and reused.

The organising idea is a **separation of altitudes**. Planning needs to know what
the domain contains; implementation needs to know where code goes and how it is
written; neither benefits from the other's detail loaded at the wrong moment.

```
   ontology     ──►  used when PLANNING       what exists, how it relates
   context      ──►  used when IMPLEMENTING   where code goes, how to write it
   tools        ──►  used when ACTING         what may be read, run, changed
```

## Layout

```
.ai/
├── business/
│   └── VALUE.md              Why the system exists. Value drivers V-1…V-6,
│                             requirement→value trace, success criteria, non-goals
├── context/
│   ├── INDEX.md              Which module to load, and when
│   ├── ontology/
│   │   ├── index.ttl         The domain in OWL/RDFS — authoritative
│   │   └── README.md         Human-readable projection + invariants I-1…I-10
│   ├── stack.md              Versions, services, env keys, commands
│   ├── architecture.md       Layering, concept→code map, ADR-001…006
│   ├── conventions.md        Naming, DOs/DON'Ts, Definition of Done
│   ├── testing.md            Test layers, required coverage T-1…T-15
│   └── tools.md              Permission model, search/edit/verify practice
├── router/                   One file per working mode
│   ├── plan.md  explore.md  implement.md  review.md  debug.md  self-reflect.md
├── backlog/                  Planned work
│   ├── INDEX.md  TEMPLATE.md  TASK-001…TASK-010
└── archive/                  Completed work, with outcome notes
    └── INDEX.md
```

[`../CLAUDE.md`](../CLAUDE.md) is the entry point that routes into all of this.

## The Traceability Chain

Nothing in this repository is meant to float free of a reason:

```
Value driver (V-n)  ──►  Requirement  ──►  Ontology concept  ──►  Invariant (I-n)
                                                │
                                                ▼
                                      Ticket (TASK-NNN)
                                                │
                                                ▼
                                    Code  ──►  Test (T-n)  ──►  Archive entry
```

Each link is checkable, and a break in the chain is a defect worth naming:

- A ticket with no value driver is scope creep.
- An invariant with no test is a promise nobody is keeping.
- A concept in the code that is absent from the ontology is a name nobody agreed on.
- A ticket in the archive with no outcome note is a decision that was lost.

## Worked Example

The brief for this project contains one requirement that implementations
routinely half-deliver: *notify by email on manual deletion too, not only on the
automatic 24-hour deletion*.

Here is how this layer prevents that, at each altitude:

| Layer | What it does about it |
| :--- | :--- |
| `VALUE.md` | Names it as driver **V-3** — auditable deletion — with success criterion **S-2**: `count(DeletionEvent) == count(NotificationMessage)` |
| `ontology/index.ttl` | Models `DeletionEvent` as a first-class concept where both deletion paths converge, rather than as a `deleted_at` column on two separate paths |
| `ontology/README.md` | States it as invariant **I-3**, in a form that can be checked |
| `architecture.md` | ADR-001 records *why* it is a table; the deletion-path diagram makes `DeleteDocument` the only entry point |
| `conventions.md` | "Don't delete a `Document` outside `DeleteDocument`" — the DON'T list's first entry |
| `backlog/TASK-006` | Builds that single path *before* either caller exists, so neither can bypass it |
| `testing.md` | **T-6** and **T-7** — one per trigger — named as the project's acceptance tests |

By the time anyone writes a controller, deleting a document without notifying has
become the awkward thing to do. That is the point: the structure carries the
requirement, instead of a reviewer having to remember it.

## Maintenance

- Context describes the **current** system. When something changes, edit in
  place — git holds the history.
- A decision between real alternatives becomes an ADR in `architecture.md`.
  Four lines: what, why, what it cost.
- A new or renamed concept updates `ontology/index.ttl` **in the same change**.
- Drift between a module and the code is a defect. A stale context file is worse
  than a missing one, because it is trusted.
