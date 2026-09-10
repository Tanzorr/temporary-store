# Backlog

Planned work. A ticket lives here until it is done, then moves to
[`../archive/`](../archive/INDEX.md) with an outcome note.

New tickets follow [`TEMPLATE.md`](TEMPLATE.md) and are created in
[plan mode](../router/plan.md).

## Open

| ID | Title | Status | Priority | Value | Depends on |
| :--- | :--- | :--- | :--- | :--- | :--- |
| [TASK-001](TASK-001-project-skeleton.md) | Laravel + Docker Compose skeleton | `planned` | P0 | V-4 | — |
| [TASK-002](TASK-002-document-persistence.md) | `documents` table, model, factory | `planned` | P0 | V-3 | 001 |
| [TASK-003](TASK-003-upload-endpoint.md) | Async upload endpoint with server-side policy | `planned` | P1 | V-4, V-1 | 002 |
| [TASK-004](TASK-004-upload-ui.md) | Bootstrap + jQuery uploader with progress | `planned` | P1 | V-4 | 003 |
| [TASK-005](TASK-005-crud-page.md) | Document list, download, manual delete UI | `planned` | P1 | V-5 | 006 |
| [TASK-006](TASK-006-deletion-core.md) | `DeletionEvent`, `DeleteDocument`, trigger enum | `planned` | P1 | V-3, V-5 | 002 |
| [TASK-007](TASK-007-rabbitmq-publisher.md) | Notification publisher + queued job | `planned` | P1 | V-3, V-6 | 006 |
| [TASK-008](TASK-008-retention-sweep.md) | Retention sweep command + scheduler | `planned` | P1 | V-1, V-2 | 006, 007 |
| [TASK-009](TASK-009-test-suite.md) | Test suite covering T-1…T-15 | `planned` | P1 | V-3 | 008 |
| [TASK-010](TASK-010-readme.md) | `README.md` setup and verification guide | `planned` | P1 | V-2 | 008 |
| [TASK-011](TASK-011-agent-tooling.md) | `msearch` / `mread` / `mreplace` agent tooling | `planned` | P1 | V-2 | — |

## Dependency Order

```
011 (no deps — do first, everything after benefits)

001 ─► 002 ─┬─► 003 ─► 004
            │
            └─► 006 ─┬─► 005
                     ├─► 007 ─► 008 ─┬─► 009
                     │               └─► 010
                     └───────────────────┘
```

`011` is numbered last but has no dependencies and is scheduled first: it is the
tooling layer the later multi-file tickets use. IDs are never renumbered, so
**numbering does not imply order** — this table's `Depends on` column does.

`006` is the hinge: `005`, `007` and `008` all depend on the single deletion path
existing first. That ordering is deliberate — building the manual delete UI before
`DeleteDocument` exists is how a project ends up with two deletion code paths and
a missing notification (invariant **I-3**).

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
