# Backlog

Planned work. A ticket lives here until it is done, then moves to
[`../archive/`](../archive/INDEX.md) with an outcome note.

New tickets follow [`TEMPLATE.md`](TEMPLATE.md) and are created in
[plan mode](../router/plan.md).

## Open

| ID | Title | Status | Priority | Value | Depends on |
| :--- | :--- | :--- | :--- | :--- | :--- |
| [TASK-008](TASK-008-retention-sweep.md) | Retention sweep command + scheduler | `planned` | P1 | V-1, V-2 | 006, 007 |
| [TASK-009](TASK-009-test-suite.md) | Test suite covering T-1…T-15 | `planned` | P1 | V-3 | 008 |
| [TASK-010](TASK-010-readme.md) | `README.md` setup and verification guide | `planned` | P1 | V-2 | 008 |

## Dependency Order

```
001 ─► 002 ─┬─► 003 ─► 004
            │
            └─► 006 ─┬─► 005
                     └─► 007 ─► 008 ─┬─► 009
                                     └─► 010
```

`006` is the hinge: `005`, `007` and `008` all depend on the single deletion path
existing first. That ordering is deliberate — building the manual delete UI before
`DeleteDocument` exists is how a project ends up with two deletion code paths and
a missing notification (invariant **I-3**).

IDs are never renumbered, so **numbering does not imply order** — this table's
`Depends on` column does. `TASK-011` (project-local batch search/edit tooling)
was written and then cut: it shipped nothing the reviewer runs, and duplicated
tools the agent already has.

## Conventions

- **ID** — `TASK-NNN`, never reused, never renumbered.
- **Filename** — `TASK-NNN-<kebab-slug>.md`.
- **Status** — `planned` → `in-progress` → `done`, or `blocked` with the reason
  in the ticket.
- **Priority** — P0 blocks everything · P1 required for delivery · P2 quality ·
  P3 nice to have.
- **Value** — every ticket traces to a driver in
  [`../business/VALUE.md`](../business/VALUE.md). A ticket that traces to none
  does not belong in this project.
- This table and the ticket files must agree. When they drift, the ticket file wins.
