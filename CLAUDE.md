# Temporary Document Store — Agent Entry Point

A web application for storing PDF and DOCX files with a bounded retention window.
Files are deleted automatically after 24 hours or manually by an operator, and
**every** deletion publishes a notification message to RabbitMQ.

This file routes to the spec-driven layer in [`.ai/`](.ai/README.md). Read the
relevant section below before acting — not this whole file, and not every context
module.

---

## 0. BE BRIEF

Whoever reviews this repository may have twenty minutes, not an afternoon. Every
document competes for that time, and length is not evidence of rigour.

- **Lead with the answer.** Context after it, or not at all.
- **One fact in one place.** If it is stated in another file, link — never restate.
- **Cut any sentence that survives its own deletion.** If removing it loses
  nothing, it was costing the reader for free.
- **Tables and lists** wherever the content is a set of items.
- **No preamble, no recap.** Don't announce what you are about to say, and don't
  summarise what you just said.
- **Say the trade-off, not the sales pitch.** "Costs an extra table" beats three
  sentences of justification.

Applies to documentation, tickets, commit messages, code comments, and replies to
the user alike. When something genuinely needs length — acceptance criteria,
invariants — it earns it by being checkable, not by being thorough-sounding.

---

## 1. ONTOLOGY

The domain model is authoritative and lives in
[`.ai/context/ontology.md`](.ai/context/ontology.md) — concepts, relations,
invariants I-1…I-10, open questions. One file, no second copy.

**Concepts:** `Document` · `StoredObject` · `UploadSession` · `UploadPolicy` ·
`RetentionPolicy` · `RetentionSweep` · `DeletionEvent` · `DeletionTrigger` ·
`NotificationMessage` · `MessageQueue` · `NotificationRecipient`

Rules that apply everywhere:

- **Names come from the ontology.** The class is `Document`, not `File` or `Upload`.
- **A new concept means updating `ontology.md`** in the same change.
- **The invariants I-1…I-10 are not preferences.** Breaking one is a bug.

The one to remember without looking it up:

> **I-3** — Every deletion, manual or automatic, produces exactly one
> `DeletionEvent`, and every `DeletionEvent` emits exactly one
> `NotificationMessage`. `App\Services\DeleteDocument` is the **only** place a
> `Document` may be deleted.

---

## 2. ROUTING

Pick the mode that matches the work, load that file, follow it.

| Mode | File | Use when | Load |
| :--- | :--- | :--- | :--- |
| **implement** | [`.ai/router/implement.md`](.ai/router/implement.md) | Building what a ticket describes | The ticket, [`conventions.md`](.ai/context/conventions.md), [`architecture.md`](.ai/context/architecture.md), [`stack.md`](.ai/context/stack.md), [`testing.md`](.ai/context/testing.md) |
| **review** | [`.ai/router/review.md`](.ai/router/review.md) | Judging a diff before commit | The diff, the ticket, [`conventions.md`](.ai/context/conventions.md), the invariants |

Anything else — answering a question, chasing a bug, shaping a new ticket — needs
no mode file. Read [`context/INDEX.md`](.ai/context/INDEX.md) and then only what
the question actually needs. A question changes nothing on disk.

Do not load everything every time. Loading implementation detail while shaping a
ticket produces tickets that prescribe code instead of outcomes.

---

## 3. WORK TRACKING

- **Planned:** [`.ai/backlog/INDEX.md`](.ai/backlog/INDEX.md) — `TASK-001`…`TASK-010`
- **Completed:** [`.ai/archive/INDEX.md`](.ai/archive/INDEX.md), each with an outcome note
- **Template:** [`.ai/backlog/TEMPLATE.md`](.ai/backlog/TEMPLATE.md)

Work is done against a ticket. If there is no ticket for what you are about to
do, either write one (plan mode) or say that the request falls outside the
backlog — do not silently expand scope.

A ticket is done only when every item of the Definition of Done in
[`conventions.md`](.ai/context/conventions.md) holds. Then it moves to the archive.

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
