# Tool Usage

Which tool for which job, and what the agent is permitted to touch. The
machine-enforced half of this lives in
[`../../.claude/settings.json`](../../.claude/settings.json); this file explains
the intent behind it.

## Permission Model

Three tiers, deliberately:

| Tier | Contents | Rationale |
| :--- | :--- | :--- |
| **Allowed, no prompt** | Reading anything in the repo; read-only shell (`rg`, `find`, `jq`, `ls`, `git status/log/diff`); running tests, `pint`, `artisan` inspection commands | These are reversible and produce no side effects. Prompting on them wastes the reviewer's attention on noise |
| **Ask first** | `composer require`, `npm install`, `artisan migrate:fresh`, `docker compose down -v`, `git commit`/`push` | Each changes durable state — dependencies, schema, volumes, history |
| **Denied** | Reading `.env`, `*.key`, `*.pem`, `auth.json`; `rm -rf`; `git push --force`; writing outside the project root | The agent has no legitimate need for a live secret, and no need to destroy data |

`.env` is denied on purpose. The agent needs to know the *name*
`DELETION_NOTIFY_EMAIL` — which is in [`stack.md`](stack.md) and `.env.example` —
never its value. This is invariant I-9 enforced by tooling rather than by
discipline.

## Searching

Read a file when you know which one. Search when you do not.

```bash
rg 'DeletionEvent' --type php              # symbol across the codebase
rg -n 'expires_at' app/ database/          # scoped to directories
rg -l 'NotificationPublisher'              # which files, not which lines
rg -A 5 -B 5 'function handle' app/Services/
find . -name '*.blade.php' -not -path './vendor/*'
jq '.require' composer.json                # structured files → jq, not grep
```

- Prefer `rg` to `grep`; it respects `.gitignore` and skips `vendor/`.
- Always exclude `vendor/` and `node_modules/`. A match there is somebody else's
  code and will mislead you.
- Search for the *ontology term* first (`DeletionEvent`, `RetentionPolicy`). If a
  domain concept has no hits, it is not implemented yet — that is a finding.

## Editing

- **Read before you edit.** Always. An edit against remembered content is a guess.
- Prefer targeted string replacement over rewriting a file. A whole-file rewrite
  produces an unreviewable diff and quietly drops things you did not read.
- One concern per edit. Do not reformat a file you are also changing — the
  reformatting hides the change.
- Never edit `vendor/`, `composer.lock` (regenerate it), or generated assets.

## Verifying

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose exec app php artisan route:list
docker compose exec app php artisan schedule:list
docker compose ps
docker compose logs --tail=50 queue scheduler
```

Run the check. Do not reason about whether it would pass — the point of a check
is that it can surprise you.

## RabbitMQ Inspection

Useful when verifying the notification path by hand:

```bash
docker compose exec rabbitmq rabbitmqctl list_queues name messages
docker compose exec rabbitmq rabbitmqadmin get queue=document.deletions count=5
```

Management UI: <http://localhost:15672> (`guest`/`guest` in dev).

## Hard Rules

1. **Never read `.env`.** Read `.env.example` instead.
2. **Never run a destructive command without being asked**: `migrate:fresh`,
   `docker compose down -v`, `rm -rf`, `git push --force`.
3. **Never commit or push unless explicitly asked.**
4. **Never install a dependency without raising it first** — see the DON'T list
   in [`conventions.md`](conventions.md).
5. **Never write outside the project root.** Scratch work goes in a temp dir and
   does not get committed.
6. **Report what actually happened.** If a command failed, show the output. A
   skipped step is stated, not omitted.
