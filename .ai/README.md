# `.ai/` — Spec-Driven Development Layer

The knowledge needed to work on this repository correctly, version-controlled and
reviewed like code rather than kept in prompts.

Prompting per task produces per-task quality: each session re-derives the domain,
invents its own names, re-decides settled questions. This layer moves that
knowledge into the repository, where it is corrected once and reused.

The organising idea is **separation of altitudes** — neither level benefits from
the other's detail loaded at the wrong moment:

```
ontology  ──►  PLANNING       what exists, how it relates
context   ──►  IMPLEMENTING   where code goes, how to write it
tools     ──►  ACTING         what may be read, run, changed
```

## Layout

```
.ai/
├── business/VALUE.md         Value drivers V-1…V-6, requirement trace,
│                             success criteria, non-goals
├── context/
│   ├── INDEX.md              Which module to load, and when
│   ├── ontology.md           The domain — concepts, relations, I-1…I-10
│   ├── stack.md              Versions, services, env keys, commands
│   ├── architecture.md       Layering, concept→code map, ADR-001…006
│   ├── conventions.md        Naming, design principles, DOs/DON'Ts, DoD
│   ├── testing.md            Test layers, required coverage T-1…T-15
│   └── tools.md              Permission model, search/edit/verify practice
├── router/                   implement · review — the two modes that change
│                             something, or judge what changed
├── backlog/                  INDEX · TEMPLATE · TASK-001…TASK-010
└── archive/                  Completed tickets, with outcome notes
```

Deliberately absent: a mode file per activity, and a formal `.ttl` serialisation
of eleven concepts. Both were written and both were cut — they cost maintenance
and bought nothing a reader of this repository can use.

[`../CLAUDE.md`](../CLAUDE.md) is the entry point that routes into all of this.
A worked example of the layers cooperating is in the root
[`README.md`](../README.md).

## The Traceability Chain

```
Value driver (V-n) ──► Requirement ──► Ontology concept ──► Invariant (I-n)
                                              │
                                              ▼
                                    Ticket (TASK-NNN)
                                              │
                                              ▼
                            Code ──► Test (T-n) ──► Archive entry
```

Each link is checkable, and a break in it is a defect worth naming:

- A ticket with no value driver is scope creep.
- An invariant with no test is a promise nobody is keeping.
- A concept in the code absent from the ontology is a name nobody agreed on.
- A concept in the ontology with no home in the code is a name nobody needed.
- An archived ticket with no outcome note is a decision that was lost.

## Maintenance

- Context describes the **current** system. Edit in place; git holds the history.
- A decision between real alternatives becomes an ADR: what, why, what it cost.
- A new or renamed concept updates [`context/ontology.md`](context/ontology.md)
  **in the same change**.
- Drift between a module and the code is a defect. A stale context file is worse
  than a missing one, because it is trusted.
