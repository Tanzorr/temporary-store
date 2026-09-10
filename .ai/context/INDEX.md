# Context Index

Load-bearing knowledge for implementation. The router ([`../router/`](../router))
tells you *which* of these to read for a given mode — do not read all of them
every time.

| Module | Read it when | Answers |
| :--- | :--- | :--- |
| [`ontology.md`](ontology.md) | Planning, modelling, any change to what entities mean | Concept table, relation graph, invariants I-1…I-10, open questions |
| [`stack.md`](stack.md) | Before running or wiring anything | Versions, services, ports, env vars, how to run things |
| [`architecture.md`](architecture.md) | Before adding a class or choosing where code goes | Layering, where each ontology concept lives in code, ADRs |
| [`conventions.md`](conventions.md) | Every time you write code | Naming, structure, design principles (SOLID, refactoring vocabulary), DOs/DON'Ts, Definition of Done |
| [`testing.md`](testing.md) | Before writing a test or claiming something works | Test layers, what must be covered, how to prove an invariant |
| [`tools.md`](tools.md) | When searching, editing or inspecting the repo | Which tool for which job, permission model, forbidden operations |

## Reading Order for a Cold Start

1. [`../business/VALUE.md`](../business/VALUE.md) — why the system exists
2. [`ontology.md`](ontology.md) — what the domain contains
3. [`stack.md`](stack.md) — what is actually installed
4. [`architecture.md`](architecture.md) — where things go
5. [`conventions.md`](conventions.md) — how to write them

## Maintenance Rule

These files describe the current system, not its history. When a decision
changes, **edit the module in place** and record the change as an ADR entry in
[`architecture.md`](architecture.md). Do not accumulate "previously we…" prose —
git already stores that.

A context module that has drifted from the code is worse than no module, because
it is trusted. If you notice drift while working, fixing it is part of the task,
not a separate one.
