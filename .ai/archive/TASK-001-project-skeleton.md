# TASK-001 — Laravel + Docker Compose skeleton

| Field | Value |
| :--- | :--- |
| **Status** | `done` |
| **Priority** | P0 |
| **Value driver** | V-4 (nothing is usable until the stack runs) |
| **Depends on** | — |
| **Created** | 2026-09-10 |

## Goal

A reviewer clones the repository, runs one command, and reaches a working Laravel
page with MySQL and RabbitMQ up. Everything else in the backlog is blocked on this.

## Ontology Touchpoints

**Concepts:** `tds:MessageQueue` (broker provisioned, not yet used)
**Invariants:** I-9 (no secret committed)
**New concepts:** none

## Acceptance Criteria

- [ ] AC-1 — `docker compose up -d --build` starts six services: `app`, `web`,
      `mysql`, `rabbitmq`, `queue`, `scheduler` (see [`../context/stack.md`](../context/stack.md)).
- [ ] AC-2 — `http://localhost:8080` returns the Laravel welcome page.
- [ ] AC-3 — `php artisan migrate` succeeds against the `mysql` service.
- [ ] AC-4 — RabbitMQ management UI is reachable at `http://localhost:15672`.
- [ ] AC-5 — `queue` and `scheduler` run as **separate containers**, and
      `docker compose ps` shows both healthy.
- [ ] AC-6 — `.env.example` contains every key from `stack.md` with placeholder
      values; `.env` is gitignored and contains no real address or credential.
- [ ] AC-7 — Config files `retention.php`, `uploads.php`, `notifications.php`,
      `rabbitmq.php` exist and read their values via `env()` — the only place
      `env()` may be called.
- [ ] AC-8 — `php artisan test` runs (the default suite may be all that exists).
- [ ] AC-9 — A 10 MB POST reaches PHP. Nginx `client_max_body_size` **and** PHP's
      `upload_max_filesize` / `post_max_size` all exceed `UPLOAD_MAX_SIZE_BYTES`.
- [ ] AC-10 — A clean clone needs Docker and nothing else — no host PHP, Composer
      or npm, and no `npm run build` before AC-2 passes.
- [ ] AC-11 — Files the containers write into the mounted tree (`vendor/`,
      `storage/logs/`) are owned by the host user, not `root`.

## Out of Scope

- Any domain table or model — `TASK-002`.
- Any RabbitMQ publishing code — `TASK-007`. This ticket only provisions the broker.
- README content — `TASK-010`.

## Notes

The project root is not empty — `.ai/`, `CLAUDE.md`, `README.md`, `.gitignore` and
`CHANGELOG.md` are already here, so `composer create-project` refuses to run in
place. Generate the skeleton elsewhere and copy it in, keeping the existing
`README.md` and `.gitignore`: the skeleton ships its own and they would overwrite
work already done.

**The upload size limit is enforced at two layers, and both fail silently.** Nginx
`client_max_body_size` defaults to 1 MB and returns `413` before PHP-FPM is ever
reached; PHP's `upload_max_filesize` / `post_max_size` below `UPLOAD_MAX_SIZE_BYTES`
surface as an empty request rather than a validation message. Neither looks like a
size problem in the logs. Hence AC-9.

Use a queue driver backed by the database or Redis for Laravel's own job queue.
RabbitMQ here is the **outbound notification bus**, not Laravel's queue backend;
conflating the two makes the notification path impossible to test in isolation.

`app`, `queue` and `scheduler` share one image and one mounted tree. Order their
startup so exactly one of them installs dependencies — three concurrent
`composer install` runs into the same `vendor/` corrupt it. `depends_on` with
`condition: service_healthy` is enough, provided `app`'s healthcheck passes only
after its bootstrap finished.

Laravel 11's `welcome.blade.php` guards `@vite` behind a
`file_exists(public_path('build/manifest.json'))` check with an inline-CSS
fallback, so AC-2 holds with no build step — consistent with ADR-005. Verified
against the `11.x` branch on 2026-09-10.

Do not migrate from the container entrypoint. AC-3 is the reviewer running
`migrate` and seeing it succeed; a container that migrates on boot hides the
failure it is supposed to reveal.

---

## Outcome

**Completed:** 2026-09-10
**What was built:** Laravel 11 + PHP 8.2 skeleton copied in from a scaffold
build, all six `docker-compose.yml` services (`app`, `web`, `mysql`,
`rabbitmq`, `queue`, `scheduler`), a shared `docker/php/Dockerfile` (host-UID
`appuser`, `pdo_mysql`/`sockets`/`pcntl`/`zip`/`opcache`/`procps`/`git`), an
`entrypoint.sh` that runs `composer install`/`.env`/`APP_KEY` bootstrap once
(gated on `$1 = php-fpm`) so `queue`/`scheduler` never race `app` on the
shared bind mount, the four `config/*.php` domain files, and `.env.example`
with every key from `stack.md`.
**Deviations from the plan:**
- `SESSION_DRIVER`/`CACHE_STORE` set to `file` instead of Laravel 11's
  stock `database` default. With `database`, the welcome page 500s until
  `migrate` runs (session/cache tables don't exist yet), which breaks the
  Goal's "one command → working page" before AC-3. `QUEUE_CONNECTION`
  stays `database` per this ticket's note, so `queue` legitimately can't
  start until `migrate` runs — expected, and why `restart: unless-stopped`
  is on every service (a crashed worker must show as *Restarting*, not
  silently *Exited*, per `stack.md`'s healthcheck rationale).
- `docker/rabbitmq/rabbitmq.conf` sets `loopback_users.guest = false`.
  RabbitMQ's default `guest` user only authenticates from its own
  loopback; a container on the compose network is not "localhost" to the
  broker, so `guest`/`guest` (as given in `stack.md`'s example) would
  otherwise never connect.
- Removed `laravel/sail` and the Vite/Tailwind/npm scaffolding the
  skeleton ships with — redundant with this custom Compose setup and
  contrary to ADR-005 (no build step). `welcome.blade.php`'s existing
  `@vite`/inline-CSS fallback needed no change.
- `composer.lock` was first resolved against the host's PHP 8.3, which
  locked `laravel/pint` to a version requiring PHP ^8.3 — incompatible
  with the pinned `php:8.2-fpm` image. Caught by testing AC-10 for real
  (bootstrapping a vendor-free copy of the tree through the container
  alone, not just reasoning about the entrypoint); fixed by relocking
  inside the `app` container. `git` was added to the image because
  Composer's downgrade path shells out to it even under `--prefer-dist`.
- Compose variables (`DB_DATABASE`, `RABBITMQ_USER`, `USER_ID`, …) all
  carry inline `${VAR:-default}` fallbacks matching `.env.example`, so
  `docker compose up -d --build` works with zero prior steps — matching
  the Goal literally, not just "after `cp .env.example .env`".
**Invariants verified:** I-9 — `.env.example` holds only placeholders
(`ops@example.com`, `secret`, `guest`/`guest`); `.env` stays gitignored and
was never read by the agent.
**Follow-ups raised:** none. `TASK-010` should document the manual
`cp .env.example .env` (optional — compose defaults cover it) and
`php artisan migrate` steps.
