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
| `mysql` | 3306 | **33061** | Volume `mysql-data`. Deliberately not 3306: a reviewer with a local MySQL would get a port clash, which is the likeliest "it did not come up on my machine" failure |
| `rabbitmq` | 5672 / 15672 | 5672 / 15672 | Management UI at :15672 |
| `queue` | — | — | `php artisan queue:work`, same image as `app` |
| `scheduler` | — | — | `php artisan schedule:work`, drives the retention sweep |

`queue` and `scheduler` are **separate containers**, not background processes
inside `app`. A crashed worker must be visible as a restarting container, not
hidden inside a healthy-looking web container. This is what protects success
criterion S-1 and the "scheduler not running" risk in `VALUE.md`.

Every service declares a **healthcheck**. Without one, `queue` and `scheduler`
report `running` forever — including while the worker inside them is dead, which
is the exact failure the previous paragraph exists to expose. `pgrep -f` on the
artisan process is enough, and it is why the image installs `procps`.

### The `app` image

`php:8.2-fpm`, built once and shared by `app`, `queue` and `scheduler`.

| Extension | Needed by |
| :--- | :--- |
| `pdo_mysql` | Eloquent |
| `sockets` | `php-amqplib` requires it outright — without it `composer require` fails, not just the connection |
| `pcntl` | `queue:work` shutting down cleanly on `SIGTERM`. Without it a restart kills a job mid-flight |
| `zip` | Composer unpacking dist archives |
| `opcache` | Throughput. Cheap, and the only one here that is merely nice to have |

`fileinfo` (content MIME detection, ADR-006), `mbstring` (also required by
`php-amqplib`) and `pdo_sqlite` (the in-memory test database) ship enabled in the
official image. Confirm with `php -m` rather than assuming — they are the kind of
thing a base-image change removes silently.

Add nothing else. An extension in the image is a thing the reviewer has to trust.

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
