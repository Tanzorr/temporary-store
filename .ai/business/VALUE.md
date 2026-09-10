# Business Value — Temporary Document Store

The document that answers *why this exists*. Every ticket in
[`../backlog/`](../backlog/INDEX.md) must trace to a value driver here. A ticket
that traces to none is either mis-scoped or should be dropped.

---

## 1. The Problem

Organisations exchange PDF and DOCX files that are only relevant for a short
time: a signed form, a quote, a scan sent to a colleague, an export handed to a
contractor. They get emailed as attachments or dropped into a permanent drive.

Both defaults are bad in the same way — **the file outlives its purpose**:

- Attachments sit in inboxes and backups indefinitely, spread across devices
  nobody can inventory.
- Shared drives accumulate one-off files that nobody dares delete, because
  nobody remembers whether they still matter.

The cost is not storage. Storage is cheap. The cost is that every retained copy
of a document is a liability with no owner: a data-protection surface, an
audit finding, a leak waiting for a misconfigured share link.

## 2. The Proposition

**A file drop where expiry is the default, not a chore.**

Upload a document, get a record of it, and know it disappears on its own within
a bounded window — with a notification confirming it is gone. Deletion is the
product, not a maintenance task bolted on afterwards.

Two properties make this worth building rather than buying a generic drive:

1. **Bounded by construction.** Retention is not a setting someone can forget to
   apply. Every document gets a deadline at upload time; nothing persists past it.
2. **Deletion is observable.** Removal is announced on a message bus, so an
   operator learns that the data is gone without having to check. That turns a
   silent background job into evidence.

## 3. Stakeholders

| Stakeholder | What they need | How the system serves it |
| :--- | :--- | :--- |
| **Sender** (uploads) | Get a file to someone without it living forever in their inbox | Async upload with immediate feedback; a bounded lifetime they don't have to manage |
| **Operator** (runs it) | Confidence the retention promise is actually kept | CRUD page listing what is currently held; a notification per deletion |
| **Data-protection owner** | A defensible retention story | A single policy governing every document, and a per-deletion record of when and why |
| **Downstream systems** | To react when a document goes away | A durable message on the broker, versioned and de-duplicable |

## 4. Value Drivers

| ID | Driver | Why it is worth money | Realised by |
| :--- | :--- | :--- | :--- |
| **V-1** | Reduced data-retention exposure | Files that no longer exist cannot leak, cannot be subpoenaed, cannot appear in a breach report. Every hour of retention avoided is risk avoided. | `RetentionPolicy`, `RetentionSweep` |
| **V-2** | Zero-effort compliance | Retention is enforced by a scheduled job, not by human discipline. The policy holds even when nobody is looking. | `RetentionPolicy` as single source of truth |
| **V-3** | Auditable deletion | "The file was deleted" is backed by a `DeletionEvent` and a published notification, not by an assumption. | `DeletionEvent`, `NotificationMessage` |
| **V-4** | Low friction for the sender | If uploading is slower than an email attachment, nobody uses it and none of the above is realised. | Async uploader, size/type feedback before submit |
| **V-5** | Operator control | An operator can delete early when a document turns out to be sensitive — and that deletion is announced exactly like an automatic one. | Manual delete on the CRUD page, same `DeletionEvent` path |
| **V-6** | Integration surface | Other systems can subscribe to deletions without this application knowing they exist. | `MessageQueue`, versioned payload |

## 5. Requirements Traced to Value

The source requirement, mapped so that no requirement is orphaned and no value
driver is unimplemented.

| Requirement (from the brief) | Value driver | Ontology concepts |
| :--- | :--- | :--- |
| Async PDF/DOCX uploader in the web UI | V-4 | `UploadSession`, `UploadPolicy` |
| File size limit (e.g. 10 MB) | V-4, V-1 | `UploadPolicy.maxSizeBytes` |
| Upload metadata persisted in the database | V-3 | `Document` |
| Separate CRUD page listing uploads | V-5 | `Document` |
| Manual deletion | V-5 | `DeletionEvent` + `ManualDeletion` |
| Automatic deletion 24h after upload | **V-1**, V-2 | `RetentionPolicy`, `RetentionSweep` |
| Publish an email notification to RabbitMQ after deletion | V-3, V-6 | `NotificationMessage`, `MessageQueue` |
| Recipient address configured in `.env` | V-2 | `NotificationRecipient.envKey` |
| Notification on **manual** deletion too, not only automatic | **V-3** | Invariant **I-3** |
| SMTP delivery itself is out of scope | V-6 | Boundary: publish, don't send |

Note the last two rows. "Notify on manual deletion too" is the requirement most
easily missed by an implementation that treats deletion as a `deleted_at` column
on two separate code paths. The ontology models `DeletionEvent` as the single
convergence point precisely so that V-3 cannot be half-delivered.

## 6. Success Criteria

Observable statements, checkable against a running system:

- **S-1** — No `Document` remains available past `expiresAt` by more than one
  sweep interval.
- **S-2** — Count of `DeletionEvent` equals count of published
  `NotificationMessage`, over any window. A gap means V-3 is broken.
- **S-3** — Both trigger codes (`manual_deletion`, `retention_expiry`) are
  observed on the queue in an end-to-end test run.
- **S-4** — An oversized or non-PDF/DOCX upload is refused **server-side**, with
  a reason the UI can display, and leaves no bytes on disk.
- **S-5** — A broker outage during deletion does not lose the notification and
  does not leave a document half-deleted (I-10).
- **S-6** — `git grep` finds no recipient email address in the repository (I-9).

## 7. Non-Goals

Named explicitly so scope cannot creep in through the back door:

- **Not** a permanent document archive or a DMS. Permanence is the failure mode.
- **Not** an email delivery system. The brief scopes this to publishing a message;
  SMTP belongs to a consumer that is not this application.
- **Not** multi-tenant or authenticated. No user accounts, ownership, or sharing
  model — adding one changes the ontology and needs its own decision record.
- **Not** a preview/rendering service. Files are stored and returned, never parsed.
- **Not** versioning. Re-uploading a file makes a new `Document`, not a revision.

## 8. Key Risks

| Risk | Impact | Mitigation |
| :--- | :--- | :--- |
| Scheduler not running in production | Files live forever; V-1 and V-2 silently lost — the worst failure because nothing errors | Sweep records `ranAt`; monitor its freshness, not just its exit code |
| Broker unreachable at deletion time | Notification lost; V-3 broken | Publish from a durable queued job with retries (I-10) |
| Notification published for a rolled-back deletion | Operator told a file is gone when it is not | Publish only after commit (I-4) |
| Purge fails but the record is marked deleted | Bytes outlive the record — the exact liability the product exists to remove | Treat purge failure as a failed deletion; retry, do not swallow |
| Untrusted filename used as a path | Path traversal | I-7: generated `storedName` on disk, `originalName` for display only |
