# Temporary Document Store

Notable changes, newest first. Every entry references the ticket that produced
it, so a reader can go from a line here to its acceptance criteria in
[`.ai/archive/`](.ai/archive/INDEX.md) without asking anyone.

Format follows [Keep a Changelog](https://keepachangelog.com/). Adding an entry
is part of the Definition of Done in
[`.ai/context/conventions.md`](.ai/context/conventions.md).

## Unreleased

**Added**
- TASK-001: Laravel 11 + Docker Compose skeleton — `app`, `web`, `mysql`,
  `rabbitmq`, `queue` and `scheduler` as six healthy, independently
  restarting services behind one `docker compose up -d --build`. Domain
  config (`retention.php`, `uploads.php`, `notifications.php`,
  `rabbitmq.php`) reads every value through `env()`.
- Spec-driven development layer in `.ai/`: business value, domain ontology,
  code context, router modes, ticketed backlog and archive.
- `CLAUDE.md` entry point routing into the ontology, the working modes and the
  tool rules.
- `.claude/settings.json` permission policy: read-broad, ask on mutating
  operations, deny secrets and destructive commands.
- `.editorconfig` covering PHP, Blade, JS, YAML, Compose files and Dockerfiles.

**Changed**
- Ontology is one Markdown file (`.ai/context/ontology.md`) instead of a Turtle
  source plus a prose projection of it. Nothing parsed the Turtle, so the second
  copy could only drift.
- Router reduced to the two modes that change something or judge what changed:
  `implement` and `review`.

**Removed**
- `TASK-011` (project-local `msearch`/`mread`/`mreplace` tooling) — it shipped
  nothing the reviewer runs and duplicated tools the agent already has.
- ADR-008's representation resolver, in favour of `ADR-007`: JSON endpoints are
  the only async surface, and jQuery updates the DOM.

**Fixed**
- `RetentionSweep` no longer implies a table that does not exist; `sweep_id` is
  documented as a correlation id, and `VALUE.md` states plainly that the
  "scheduler not running" risk is only partly mitigated.
- MySQL is published on host port `33061`, avoiding a clash with a local server.

---

## Conventions

- One entry per user-visible change. Internal refactors that change nothing
  observable belong in the ticket's outcome note, not here.
- Prefix each entry with its ticket: `TASK-006: Deletion events and the single
  deletion path.`
- Write for someone who did not do the work. "Fix bug" tells them nothing;
  "Sweep no longer publishes a duplicate notification when run twice" does.
- Sections: **Added**, **Changed**, **Fixed**, **Removed**. Omit empty ones once
  the file has real content.
