# Stack & Infrastructure

Target state. Until `TASK-001` lands, most of this describes what *will* exist —
check reality with `ls` and `docker compose ps` before assuming.

## Runtime

| Component | Version | Role |
| :--- | :--- | :--- |
| PHP | 8.2+ | Application runtime |
| Laravel | 11.x | Framework: HTTP, queues, scheduler, Eloquent |
| MySQL | 8.0 | `documents` and `deletion_events` tables |
| RabbitMQ | 3.13 (management image) | Carries `NotificationMessage` to consumers |
| Bootstrap | 5.3 | UI layout and components |
| jQuery | 3.7 | Async upload, CRUD interactions |
| Docker Compose | v2 | Local orchestration of all of the above |

Frontend is server-rendered Blade plus jQuery. **No SPA framework, no build
step** — the brief asks for Bootstrap + jQuery and adding Vite/React would be
scope the reviewer did not ask for.

## Services (docker-compose)

| Service | Container port | Host port | Notes |
| :--- | :--- | :--- | :--- |
| `app` | 9000 | — | PHP-FPM |
| `web` | 80 | 8080 | Nginx, serves `public/` |
| `mysql` | 3306 | 3306 | Volume `mysql-data` |
| `rabbitmq` | 5672 / 15672 | 5672 / 15672 | Management UI at :15672 |
| `queue` | — | — | `php artisan queue:work`, same image as `app` |
| `scheduler` | — | — | `php artisan schedule:work`, drives the retention sweep |

`queue` and `scheduler` are **separate containers**, not background processes
inside `app`. A crashed worker must be visible as a restarting container, not
hidden inside a healthy-looking web container. This is what protects success
criterion S-1 and the "scheduler not running" risk in `VALUE.md`.

## Environment Variables

Domain-specific keys beyond the Laravel defaults:

| Key | Example | Meaning |
| :--- | :--- | :--- |
| `RETENTION_TTL_HOURS` | `24` | `RetentionPolicy.ttlHours`. Lowered in tests. |
| `UPLOAD_MAX_SIZE_BYTES` | `10485760` | `UploadPolicy.maxSizeBytes` (10 MB) |
| `UPLOAD_DISK` | `local` | `StoredObject.diskName` |
| `DELETION_NOTIFY_EMAIL` | `ops@example.com` | `NotificationRecipient.emailAddress` |
| `RABBITMQ_HOST` | `rabbitmq` | Broker host |
| `RABBITMQ_PORT` | `5672` | Broker port |
| `RABBITMQ_USER` / `RABBITMQ_PASSWORD` | `guest` / `guest` | Credentials (dev only) |
| `RABBITMQ_EXCHANGE` | `documents` | `MessageQueue.exchangeName` |
| `RABBITMQ_QUEUE` | `document.deletions` | `MessageQueue.queueName` |
| `RABBITMQ_ROUTING_KEY` | `document.deleted` | `NotificationMessage.routingKey` |

Every one of these is mirrored in `.env.example` with a **non-real** value.
`.env` itself is never committed and never read by the agent — see
[`tools.md`](tools.md).

Read them through `config/*.php` only. `env()` outside a config file returns
`null` once `config:cache` runs in production — that is a real outage, not a
style preference.

## Commands

```bash
docker compose up -d --build         # bring the stack up
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app php artisan schedule:list
docker compose logs -f queue scheduler
```

Manual sweep, for testing the retention path without waiting:

```bash
docker compose exec app php artisan documents:sweep-expired
```

## Boundaries

- The application **publishes** to RabbitMQ. It does not consume, and it does not
  send email. A consumer may be added later as a separate concern.
- Storage is accessed through Laravel's `Storage` facade with a named disk, never
  through raw `fopen`/`unlink` on absolute paths. Swapping local for S3 must not
  touch domain code.
