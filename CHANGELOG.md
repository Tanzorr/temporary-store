# Temporary Document Store

Notable changes, newest first. Every entry references the ticket that produced
it, so a reader can go from a line here to its acceptance criteria in
[`.ai/archive/`](.ai/archive/INDEX.md) without asking anyone.

Format follows [Keep a Changelog](https://keepachangelog.com/). Adding an entry
is part of the Definition of Done in
[`.ai/context/conventions.md`](.ai/context/conventions.md).

## Unreleased

**Added**
- Spec-driven development layer in `.ai/`: business value, domain ontology
  (OWL/RDFS), code context, router modes, ticketed backlog and archive.
- `CLAUDE.md` entry point routing into the ontology, the working modes and the
  tool rules.
- `.claude/settings.json` permission policy: read-broad, ask on mutating
  operations, deny secrets and destructive commands.
- `.editorconfig` covering PHP, Blade, JS, YAML, Compose files and Dockerfiles.

**Changed**
- *(nothing yet)*

**Fixed**
- *(nothing yet)*

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
