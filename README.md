# Temporary Document Store

A web application for storing PDF and DOCX files with a bounded retention window.
Files are deleted automatically 24 hours after upload, or manually by an
operator — and **every** deletion publishes a notification message to RabbitMQ so
that an email can be sent downstream. Reasoning behind the retention promise:
[`VALUE.md`](.ai/business/VALUE.md).

**Scope boundary:** the application *publishes* deletion notifications to
RabbitMQ. Sending the email over SMTP is deliberately out of scope, per the
brief. Full non-goals in [`VALUE.md`](.ai/business/VALUE.md) §7.

**Stack:** Laravel 11 · PHP 8.2 · MySQL 8 · RabbitMQ 3.13 ·
Bootstrap 5 + jQuery · Docker Compose — see [`stack.md`](.ai/context/stack.md).

---

## Prerequisites

- Docker
- Docker Compose v2 (the `docker compose` subcommand)

Nothing else is needed on the host — no local PHP, no Composer.

## Setup

```bash
git clone <repo-url> temperary_store && cd temperary_store
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Open **http://localhost:8080**.

## Environment variables

Defined in `.env.example`; the values below are the Docker Compose defaults, not
secrets.

| Variable | Default | Meaning |
| :--- | :--- | :--- |
| `APP_URL` | `http://localhost:8080` | Base URL the app is served from |
| `DB_DATABASE` | `temporary_document_store` | MySQL database name |
| `DB_USERNAME` | `laravel` | MySQL user |
| `DB_PASSWORD` | `secret` | MySQL password (placeholder — local only) |
| `RABBITMQ_HOST` | `rabbitmq` | MessageQueue host |
| `RABBITMQ_PORT` | `5672` | MessageQueue AMQP port |
| `RABBITMQ_USER` | `guest` | MessageQueue user |
| `RABBITMQ_PASSWORD` | `guest` | MessageQueue password (placeholder) |
| `RABBITMQ_EXCHANGE` | `documents` | Exchange `NotificationMessage` is published to |
| `RABBITMQ_QUEUE` | `document.deletions` | Queue bound to that exchange |
| `RABBITMQ_ROUTING_KEY` | `document.deleted` | Routing key on publish |
| `UPLOAD_DISK` | `local` | Filesystem disk `StoredObject`s are written to |
| `UPLOAD_MAX_SIZE_BYTES` | `10485760` (10 MB) | Upload admission ceiling |
| `RETENTION_TTL_HOURS` | `24` | Hours a `Document` lives before the sweep collects it |
| `DELETION_NOTIFY_EMAIL` | *(placeholder, e.g. `ops@example.test`)* | `NotificationRecipient` address — fake, never a real mailbox (I-9) |

## Verification

Run each step against `http://localhost:8080` (UI) or with `curl` (API — same
routes the UI calls).

**1. Upload a PDF**

```bash
curl -F "file=@/path/to/sample.pdf" http://localhost:8080/documents
```
→ `201`, JSON with `uuid`, `expires_at`.

**2. Upload an oversized file and see it refused**

```bash
truncate -s 11M /tmp/too-big.pdf
curl -i -F "file=@/tmp/too-big.pdf" http://localhost:8080/documents
```
→ `422`, `{"code":"too_large"}`.

**3. List documents**

Open http://localhost:8080/documents — the file from step 1 is listed.

**4. Delete manually, observe the notification**

```bash
curl -X DELETE http://localhost:8080/documents/<uuid-from-step-1>
docker compose exec rabbitmq rabbitmqadmin get queue=document.deletions ackmode=ack_requeue_false
```
→ the deletion returns `200`; the `rabbitmqadmin get` prints the
`NotificationMessage` payload with `"trigger":"manual_deletion"`.

**5. See the 24-hour deletion without waiting 24 hours**

```bash
# in .env: RETENTION_TTL_HOURS=0
docker compose restart app queue scheduler
curl -F "file=@/path/to/sample.pdf" http://localhost:8080/documents   # now already expired
docker compose exec app php artisan documents:sweep-expired
docker compose exec rabbitmq rabbitmqadmin get queue=document.deletions ackmode=ack_requeue_false
```
→ the sweep deletes the document; the second `rabbitmqadmin get` prints a
message with `"trigger":"retention_expiry"`. Revert `RETENTION_TTL_HOURS` and
restart the containers afterwards.

### Sample notification payload

```json
{
  "schema_version": 1,
  "event_id": "b2b9e6b0-...-uuid",
  "occurred_at": "2026-09-10T14:32:01+00:00",
  "trigger": "manual_deletion",
  "recipient": "ops@example.test",
  "subject": "Document deleted: quarterly-report.pdf",
  "document": {
    "uuid": "a1c4f2d8-...-uuid",
    "original_name": "quarterly-report.pdf",
    "size_bytes": 204800,
    "mime_type": "application/pdf",
    "uploaded_at": "2026-09-09T14:32:01+00:00",
    "expires_at": "2026-09-10T14:32:01+00:00"
  }
}
```

`trigger` is one of `manual_deletion` / `retention_expiry` — the two
`DeletionTrigger` values; the shape is otherwise identical for both (I-3).

### Test suite

```bash
docker compose exec app php artisan test
```
→ all tests pass (`Tests: NN passed`, no failures).

---

## How this project is built

Spec-first: the domain model, the code rules and the work items are versioned
artifacts that a human and an AI agent both read before writing anything —
ontology (`.ai/context/ontology.md`), architecture decisions, a router that
picks context by task type, and a backlog/archive of tickets with acceptance
criteria and outcome notes. Layout and rationale: [`.ai/README.md`](.ai/README.md).
