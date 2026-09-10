# Temporary Document Store — Agent Entry Point

A web application for storing PDF and DOCX files with a bounded retention window.
Files are deleted automatically after 24 hours or manually by an operator, and
**every** deletion publishes a notification message to RabbitMQ.

This file routes to the spec-driven layer in [`.ai/`](.ai/README.md). Read the
relevant section below before acting — not this whole file, and not every context
module.

---

## 1. ONTOLOGY

The domain model is authoritative and lives in
[`.ai/context/ontology/index.ttl`](.ai/context/ontology/index.ttl) (W3C Turtle,
OWL 2 + RDFS). A human-readable projection with the invariants is in
[`.ai/context/ontology/README.md`](.ai/context/ontology/README.md).

**Concepts:** `Document` · `StoredObject` · `UploadSession` · `UploadPolicy` ·
`RetentionPolicy` · `RetentionSweep` · `DeletionEvent` · `DeletionTrigger` ·
`NotificationMessage` · `MessageQueue` · `NotificationRecipient`

Rules that apply everywhere:

- **Names come from the ontology.** The class is `Document`, not `File` or `Upload`.
- **A new concept means updating `index.ttl`** in the same change.
- **The invariants I-1…I-10 are not preferences.** Breaking one is a bug.

The one to remember without looking it up:

> **I-3** — Every deletion, manual or automatic, produces exactly one
> `DeletionEvent`, and every `DeletionEvent` emits exactly one
> `NotificationMessage`. `App\Services\DeleteDocument` is the **only** place a
> `Document` may be deleted.

---

## 2. ROUTING

Pick the mode that matches the work, load that file, follow it.

| Mode | File | Use when |
| :--- | :--- | :--- |
| **plan** | [`.ai/router/plan.md`](.ai/router/plan.md) | Turning an intent into a ticket |
| **explore** | [`.ai/router/explore.md`](.ai/router/explore.md) | Answering a question. **Changes nothing** |
| **implement** | [`.ai/router/implement.md`](.ai/router/implement.md) | Building what a ticket describes |
| **review** | [`.ai/router/review.md`](.ai/router/review.md) | Judging a diff before commit |
| **debug** | [`.ai/router/debug.md`](.ai/router/debug.md) | Finding the cause of an observed failure |
| **self-reflect** | [`.ai/router/self-reflect.md`](.ai/router/self-reflect.md) | Before reporting done, or after something went wrong |

If no mode is stated: a question is **explore**, a request to build is
**implement**, a vague intent is **plan**.

### Context to load per mode

- **plan** → [`VALUE.md`](.ai/business/VALUE.md), ontology README, [`architecture.md`](.ai/context/architecture.md), [`backlog/INDEX.md`](.ai/backlog/INDEX.md)
- **implement** → the ticket, [`conventions.md`](.ai/context/conventions.md), [`architecture.md`](.ai/context/architecture.md), [`stack.md`](.ai/context/stack.md), [`testing.md`](.ai/context/testing.md)
- **explore / debug** → [`context/INDEX.md`](.ai/context/INDEX.md), then only what the question needs
- **review** → the diff, the ticket, [`conventions.md`](.ai/context/conventions.md), the invariants

Do not load everything every time. Loading implementation detail during planning
produces tickets that prescribe code instead of outcomes.

---

## 3. WORK TRACKING

- **Planned:** [`.ai/backlog/INDEX.md`](.ai/backlog/INDEX.md) — `TASK-001`…`TASK-010`
- **Completed:** [`.ai/archive/INDEX.md`](.ai/archive/INDEX.md), each with an outcome note
- **Template:** [`.ai/backlog/TEMPLATE.md`](.ai/backlog/TEMPLATE.md)

Work is done against a ticket. If there is no ticket for what you are about to
do, either write one (plan mode) or say that the request falls outside the
backlog — do not silently expand scope.

A ticket is done only when all seven items of the Definition of Done in
[`conventions.md`](.ai/context/conventions.md) hold. Then it moves to the archive.

---

## 4. TOOL USAGE

Full guidance: [`.ai/context/tools.md`](.ai/context/tools.md). Machine-enforced
permissions: [`.claude/settings.json`](.claude/settings.json).

**Search** — `rg` over `grep`, always excluding `vendor/` and `node_modules/`;
`jq` for JSON; `find` for paths. Search by ontology term first — the domain
vocabulary is the index into this codebase.

**Edit** — read the file before editing it. Targeted replacements, not whole-file
rewrites. One concern per edit; never reformat a file you are also changing.

**Verify** — run the check, do not reason about whether it would pass:

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose ps
```

**Hard rules**

1. Never read `.env` — use `.env.example`. The agent needs key *names*, never values.
2. Never run `migrate:fresh`, `docker compose down -v`, `rm -rf` or
   `git push --force` unless explicitly asked.
3. Never commit or push unless asked.
4. Never add a dependency without raising it first.
5. Never write outside the project root.
6. Report what actually happened. A failing command is reported with its output;
   a skipped step is stated, not omitted.

---

## 5. CURRENT STATE

The `.ai/` specification layer is complete. **No application code exists yet** —
`TASK-001` (Laravel + Docker Compose skeleton) is the first ticket and blocks
everything else.

Before assuming a file or service exists, check. Most of
[`stack.md`](.ai/context/stack.md) describes the target state, not the present one.
