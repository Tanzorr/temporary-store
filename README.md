# Temporary Document Store

A web application for storing PDF and DOCX files with a bounded retention window.
Files are deleted automatically 24 hours after upload, or manually by an
operator — and **every** deletion publishes a notification message to RabbitMQ so
that an email can be sent downstream.

> **Status: specification complete, implementation not started.**
> The `.ai/` layer below is finished. Application code begins at
> [`TASK-001`](.ai/backlog/TASK-001-project-skeleton.md). Setup and verification
> instructions are delivered by [`TASK-010`](.ai/backlog/TASK-010-readme.md) and
> will replace the *Getting Started* section here.

---

## Planned Stack

Laravel 11 · PHP 8.2 · MySQL 8 · RabbitMQ 3.13 · Bootstrap 5 + jQuery ·
Docker Compose. Details in [`.ai/context/stack.md`](.ai/context/stack.md).

## Getting Started

Not yet runnable — there is no application code. Once `TASK-001` lands:

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate
# http://localhost:8080
```

---

## How This Project Is Built

This repository is developed **spec-first**: the domain model, the code rules and
the work items are versioned artifacts that both a human and an AI agent read
before writing anything. They live in [`.ai/`](.ai/README.md), and
[`CLAUDE.md`](CLAUDE.md) is the entry point that routes into them.

```
CLAUDE.md                     Entry point: ontology · routing · tool usage
.ai/
├── business/VALUE.md         Why the system exists — value drivers, success criteria, non-goals
├── context/
│   ├── ontology/index.ttl    The domain in OWL/RDFS — 11 concepts, 23 relations, 44 attributes
│   ├── ontology/README.md    Human-readable projection + invariants I-1…I-10
│   ├── architecture.md       Layering, concept→code map, ADR-001…006
│   ├── conventions.md        Naming, DOs/DON'Ts, Definition of Done
│   ├── stack.md              Versions, services, env keys, commands
│   ├── testing.md            Test layers and required coverage T-1…T-15
│   └── tools.md              What the agent may read, run and change
├── router/                   plan · explore · implement · review · debug · self-reflect
├── backlog/                  TASK-001…TASK-010, planned work
└── archive/                  Completed work, with outcome notes
.claude/settings.json         Machine-enforced permissions for the agent
```

### Why bother

The requirement most easily lost in this brief is that a notification must be
sent on **manual** deletion, not only on the automatic 24-hour one. An
implementation that treats deletion as a `deleted_at` column ends up with two
code paths, and one of them quietly forgets to notify.

So the specification is arranged to make that impossible:

| Layer | What it does about it |
| :--- | :--- |
| `VALUE.md` | Value driver **V-3** (auditable deletion), success criterion **S-2**: `count(DeletionEvent) == count(NotificationMessage)` |
| `ontology/index.ttl` | `DeletionEvent` is a first-class concept where both deletion paths converge |
| `ontology/README.md` | Invariant **I-3**, stated so it can be checked |
| `architecture.md` | ADR-001 records why; `DeleteDocument` is the only entry point |
| `conventions.md` | "Don't delete a `Document` outside `DeleteDocument`" |
| `TASK-006` | Builds that path *before* either caller exists |
| `testing.md` | **T-6** and **T-7** — one test per trigger — as the acceptance tests |

By the time anyone writes a controller, deleting without notifying is the
awkward thing to do. The structure carries the requirement, rather than a
reviewer having to remember it.

Full rationale: [`.ai/README.md`](.ai/README.md).

---

## Scope Boundary

The application **publishes** deletion notifications to RabbitMQ. Sending the
email over SMTP is deliberately out of scope, as the brief specifies — a
consumer for that is a separate concern. See
[`.ai/business/VALUE.md`](.ai/business/VALUE.md) §7 for the full list of
non-goals.
