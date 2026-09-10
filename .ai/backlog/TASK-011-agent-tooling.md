# TASK-011 — `msearch` / `mread` / `mreplace` agent tooling

| Field | Value |
| :--- | :--- |
| **Status** | `planned` |
| **Priority** | P1 |
| **Value driver** | V-2 (the method has to be cheap to apply, or it stops being applied) |
| **Depends on** | — |
| **Created** | 2026-09-10 |

## Goal

Give the agent three project-local primitives — batch search, batch read, batch
replace — so that a change touching several files is one reviewable, atomic
operation instead of a sequence of independent edits that can half-apply.

This is the third layer of the `.ai/` design (`ontology` → `code context` →
**tools**) and the one currently missing: [`tools.md`](../context/tools.md)
documents practice and [`.claude/settings.json`](../../.claude/settings.json)
enforces permissions, but neither adds a primitive.

## Ontology Touchpoints

**Concepts:** none — this is workflow tooling, not domain code
**Invariants:** I-9 (tooling must not read secrets), plus the tool rules in
[`tools.md`](../context/tools.md)
**New concepts:** none. Deliberately: the ontology models the *document store*,
and adding developer tooling to it would blur what the ontology is for.

## Acceptance Criteria

### Shared

- [ ] AC-1 — Three executables under `bin/`: `msearch`, `mread`, `mreplace`.
      Python 3 standard library only, no install step, no dependency file.
- [ ] AC-2 — Every tool refuses to touch paths outside the project root, and
      skips `vendor/`, `node_modules/`, `.git/`, `storage/framework/` by default.
- [ ] AC-3 — Every tool refuses to read or write `.env` and `*.key` / `*.pem`
      (I-9), regardless of arguments.
- [ ] AC-4 — Distinct exit codes: `0` success · `1` no match · `2` bad usage ·
      `3` refused by policy. A caller must be able to tell "found nothing" from
      "you asked wrongly" without parsing prose.
- [ ] AC-5 — `--help` on each tool prints its format and exits `0`.

### `msearch`

- [ ] AC-6 — One interface over three back-ends: text/code (`rg`), structured
      files (`jq`), paths (`find`). Back-end chosen by flag, defaulting to `rg`.
- [ ] AC-7 — Output is stable and machine-readable: `path:line:text`, one match
      per line. `--json` emits one JSON object per line.
- [ ] AC-8 — `--files` lists matching paths only.
- [ ] AC-9 — Falls back to a pure-Python scan with a clear notice when `rg` is
      absent, rather than failing.

### `mread`

- [ ] AC-10 — Reads several files, or line ranges (`path:10-40`), in one
      invocation, each under a `=== path:from-to ===` header.
- [ ] AC-11 — Output is capped (default 2000 lines total); truncation is stated
      explicitly, never silent.

### `mreplace`

- [ ] AC-12 — Reads an edit script from a file or stdin in this format:

      ```
      file: app/Http/Controllers/DocumentController.php
      >>
      $document->delete();
      <<
      >>
      $this->deleteDocument->handle($document, DeletionTrigger::MANUAL_DELETION);
      <<
      ===
      file: app/Console/Commands/SweepExpiredDocuments.php
      >>
      ...old...
      <<
      >>
      ...new...
      <<
      ```

      `file:` names the target · `>>` … `<<` delimits a chunk · first chunk is
      the old text, second the new · `===` separates edits.
- [ ] AC-13 — **Atomic.** Every edit is validated first; if any one fails,
      nothing is written. A half-applied multi-file refactor is the failure mode
      this tool exists to prevent.
- [ ] AC-14 — An old chunk matching **zero** times is an error. Matching **more
      than once** is also an error — never a silent guess at which one was meant.
- [ ] AC-15 — `--dry-run` prints a unified diff per file and writes nothing.
- [ ] AC-16 — Whitespace and indentation are preserved exactly; matching is
      literal, not regex.
- [ ] AC-17 — On success, prints one line per file: path, edits applied.

### Integration

- [ ] AC-18 — [`../context/tools.md`](../context/tools.md) gains a section on
      when to reach for these over the built-ins, with worked examples.
- [ ] AC-19 — [`../../.claude/settings.json`](../../.claude/settings.json) allows
      `bin/msearch` and `bin/mread` without prompting, and `bin/mreplace --dry-run`
      too — but leaves a writing `bin/mreplace` to the normal edit-permission flow.
- [ ] AC-20 — Tests under `bin/tests/` cover: atomicity on a failing edit,
      ambiguous match rejected, zero match rejected, `.env` refused, path escape
      refused, dry-run writes nothing. Runnable as `python3 bin/tests/run.py`.

## Out of Scope

- Replacing the built-in Read/Edit/Grep tools. These are for **batch** work; a
  single-file edit stays a single-file edit.
- Regex or fuzzy matching in `mreplace`. Literal-only is a deliberate constraint
  — fuzzy matching is how a batch tool silently corrupts a file.
- Any AST or language awareness.
- Undo. Git is the undo.

## Notes

**Why this is not app code.** These tools never ship with the application and
never run inside the containers. They are part of the method being demonstrated,
which is why they get a ticket rather than living as undocumented scripts.

**Why Python 3 stdlib.** [`TASK-001`](TASK-001-project-skeleton.md) AC-2 promises
a reviewer needs nothing on the host but Docker — no local PHP. Writing these in
PHP would break that promise for the tooling itself. Python 3 is present on every
target dev machine and needs no package install. Record this as **ADR-007** when
implementing.

**On the edit format.** The sketch in the reference repository gives it as
`file: … >> old part << >> new part === …`, which is ambiguous about where each
chunk ends. AC-12 fixes a precise reading: `>>` opens and `<<` closes *every*
chunk, two chunks per edit. If the intent was different, this is the criterion to
change — and it should be changed here, before implementation, not after.

**Ordering vs. numbering.** This is `TASK-011` because IDs are never renumbered
([`INDEX.md`](INDEX.md) conventions), but it has no dependencies and is best done
**first** — every later ticket that touches several files benefits from it.
