# Mode: EXPLORE

Answer a question about the system. Change nothing.

## Load

Start with [`../context/INDEX.md`](../context/INDEX.md) and follow only what the
question needs. Exploring is not an excuse to load the whole context.

## Method

1. **Ask what would answer this.** A file, a symbol, a command's output? Name it
   before searching.
2. **Search by ontology term first.** `rg 'DeletionEvent' --type php`. Domain
   vocabulary is the index into this codebase (see
   [`../context/tools.md`](../context/tools.md)).
3. **Read the primary source.** The code, the migration, the config file — not a
   doc *about* them. Docs drift; code does not lie about what it does.
4. **Check the running system when the question is behavioural.**
   `php artisan route:list`, `schedule:list`, `docker compose ps`, queue depth.

## Report

- The answer first, in one or two sentences.
- Then the evidence: `file.php:42`, or the command output you actually saw.
- Then, only if relevant, what you did *not* check.

## Rules

- **No edits in this mode.** Not even a "quick fix" of something you noticed.
  Note it and offer it; do not smuggle it in.
- **Distinguish "not found" from "not implemented".** A concept with no hits is a
  finding worth reporting, not a failed search to retry differently forever.
- **Do not guess to fill a gap.** "The publisher is not wired up yet" is a useful
  answer. An invented description of how it works is not.
- **If exploring reveals drift** between a context module and the code, report it.
  Fixing it is a ticket, or part of the current one — never a silent edit here.
