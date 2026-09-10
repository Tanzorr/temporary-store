# Temporary Document Store

A web application for storing PDF and DOCX files with a bounded retention window.
Files are deleted automatically 24 hours after upload, or manually by an
operator — and **every** deletion publishes a notification message to RabbitMQ so
that an email can be sent downstream.

> **Status: specification complete, implementation not started.**
> Work begins at [`TASK-011`](.ai/backlog/TASK-011-agent-tooling.md) (tooling,
> no dependencies) and [`TASK-001`](.ai/backlog/TASK-001-project-skeleton.md)
> (Laravel + Docker). Setup instructions arrive with
> [`TASK-010`](.ai/backlog/TASK-010-readme.md).

**Planned stack:** Laravel 11 · PHP 8.2 · MySQL 8 · RabbitMQ 3.13 ·
Bootstrap 5 + jQuery · Docker Compose —
see [`stack.md`](.ai/context/stack.md).

**Scope boundary:** the application *publishes* deletion notifications. Sending
the email over SMTP is out of scope, as the brief specifies. Full non-goals in
[`VALUE.md`](.ai/business/VALUE.md) §7.

---

## How This Project Is Built

Spec-first: the domain model, the code rules and the work items are versioned
artifacts that a human and an AI agent both read before writing anything.

```
CLAUDE.md              Entry point: brevity · ontology · routing · tool usage
CHANGELOG.md           User-visible changes, each tagged with its ticket
.claude/settings.json  Machine-enforced permissions for the agent
.ai/business/          Why the system exists — value drivers, non-goals
.ai/context/           Ontology (OWL/RDFS), architecture, conventions, testing
.ai/router/            One file per working mode
.ai/backlog/           TASK-001…TASK-011, with acceptance criteria
.ai/archive/           Completed work, with outcome notes
```

Layout and rationale: [`.ai/README.md`](.ai/README.md).

### Why bother

The requirement most easily lost in this brief is that a notification must be
sent on **manual** deletion, not only on the automatic 24-hour one. An
implementation that treats deletion as a `deleted_at` column ends up with two
code paths, and one of them quietly forgets to notify.

The specification is arranged to make that impossible:

| Layer | What it does about it |
| :--- | :--- |
| `VALUE.md` | Driver **V-3** (auditable deletion), criterion **S-2**: `count(DeletionEvent) == count(NotificationMessage)` |
| `ontology/index.ttl` | `DeletionEvent` is a first-class concept where both deletion paths converge |
| `ontology/README.md` | Invariant **I-3**, stated so it can be checked |
| `architecture.md` | ADR-001 records why; `DeleteDocument` is the only entry point |
| `conventions.md` | "Don't delete a `Document` outside `DeleteDocument`" |
| `TASK-006` | Builds that path *before* either caller exists |
| `testing.md` | **T-6** and **T-7** — one test per trigger — as the acceptance tests |

By the time anyone writes a controller, deleting without notifying is the awkward
thing to do. The structure carries the requirement, rather than a reviewer having
to remember it.
