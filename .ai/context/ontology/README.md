# Ontology — Temporary Document Store

Machine-readable source: [`index.ttl`](index.ttl) (W3C Turtle, OWL 2 + RDFS).
This page is the human-readable projection of it. **The `.ttl` is authoritative** — if
this page and the Turtle disagree, the Turtle wins and this page is the bug.

The ontology is a *planning* artifact. It says what exists in the domain and how
concepts relate. It deliberately says nothing about Laravel, tables, or classes —
that mapping lives in [`../architecture.md`](../architecture.md).

---

## 1. Core Concepts (OWL Classes)

| Concept | URI | Role in the domain |
| :--- | :--- | :--- |
| **Document** | `tds:Document` | The tracked record of one uploaded PDF/DOCX — identity, metadata, deadline, status. What the user sees in the CRUD list. Not the bytes. |
| **StoredObject** | `tds:StoredObject` | The physical bytes on a storage disk backing exactly one Document. Has an independent lifetime: can be purged while the record survives as a tombstone. |
| **UploadSession** | `tds:UploadSession` | One asynchronous upload attempt from the browser: progress, outcome, and a reason when refused. A rejected session produces no Document. |
| **UploadPolicy** | `tds:UploadPolicy` | Admission rules every session is validated against: max size, allowed MIME types, allowed extensions. |
| **RetentionPolicy** | `tds:RetentionPolicy` | The rule bounding how long a Document may live. Turns `uploadedAt` into `expiresAt` (default 24h). Single source of truth for that arithmetic. |
| **RetentionSweep** | `tds:RetentionSweep` | One execution of the scheduled job that enforces the policy. Idempotent; records candidates found and rows removed. |
| **DeletionEvent** | `tds:DeletionEvent` | The fact that a Document was removed, when, and why. **The junction where both deletion paths converge.** |
| **DeletionTrigger** | `tds:DeletionTrigger` | Closed enumeration of causes: `ManualDeletion`, `RetentionExpiry`. |
| **NotificationMessage** | `tds:NotificationMessage` | The AMQP payload published after a deletion. Publishing is in scope; sending the email is not. |
| **MessageQueue** | `tds:MessageQueue` | The durable RabbitMQ destination (exchange + queue + routing key) carrying notifications onward. |
| **NotificationRecipient** | `tds:NotificationRecipient` | The operator mailbox told about deletions. Address comes from the environment, not from a Document. |

---

## 2. Relations (Object Properties)

```
UploadSession  --validatedAgainst-->      UploadPolicy
UploadSession  --producesDocument-->      Document            (inverse: wasProducedBy)
Document       --hasStoredObject-->       StoredObject        (inverse: isStoredObjectOf)
Document       --governedBy-->            RetentionPolicy     (inverse: governs)
RetentionPolicy--enforcedBy-->            RetentionSweep      (inverse: enforces)
RetentionSweep --collectsExpired-->       Document            (inverse: collectedBySweep)
Document       --hasDeletionEvent-->      DeletionEvent       (inverse: deletesDocument)
DeletionEvent  --purges-->                StoredObject        (inverse: purgedBy)
DeletionEvent  --triggeredBy-->           DeletionTrigger
DeletionEvent  --raisedDuringSweep-->     RetentionSweep      (automatic deletions only)
DeletionEvent  --emitsNotification-->     NotificationMessage (inverse: emittedBy)
Notification   --publishedTo-->           MessageQueue        (inverse: carries)
Notification   --addressedTo-->           NotificationRecipient (inverse: receives)
```

---

## 3. The Shape That Matters

Two independent paths end at the same node:

```
  operator clicks Delete ─┐
                          ├─► DeletionEvent ──► NotificationMessage ──► MessageQueue
  RetentionSweep finds  ──┘        │
  an expired Document              └──► purges StoredObject
```

This convergence is the whole reason `DeletionEvent` is modelled as a first-class
concept rather than a `deleted_at` column. The requirement *"notify on automatic
**and** manual deletion"* becomes structurally true instead of something two code
paths have to remember to do. See invariant **I-3** below.

---

## 4. Invariants

Statements that must hold at all times. Violating one is a bug, not a preference.

| # | Invariant |
| :--- | :--- |
| **I-1** | A Document exists only if its UploadSession passed `UploadPolicy` validation. Server-side validation is authoritative; the client check is a courtesy. |
| **I-2** | `expiresAt = uploadedAt + RetentionPolicy.ttlHours`, computed in one place only. |
| **I-3** | Every deletion — manual or automatic — produces exactly one `DeletionEvent`, and every `DeletionEvent` emits exactly one `NotificationMessage`. There is no code path that deletes a Document without one. |
| **I-4** | A `NotificationMessage` is published only after the deletion is durably committed. No notification for a deletion that was rolled back. |
| **I-5** | Publication carries the `DeletionEvent`'s id as the message id, so a retried publish is de-duplicable by the consumer. |
| **I-6** | A `RetentionSweep` is idempotent: running it twice over the same window deletes nothing twice and emits no duplicate notifications. |
| **I-7** | `originalName` is display data only. It never becomes a filesystem path segment or a response header without escaping. |
| **I-8** | `mimeType` is derived from file content, never trusted from the client-supplied `Content-Type`. |
| **I-9** | The recipient address is read from the environment (`envKey`), never committed to the repository. |
| **I-10** | A broker outage must not lose a notification or leave a Document half-deleted; publication is retried from durable state. |

---

## 5. Open Questions

Tracked here rather than resolved silently. Each becomes a ticket when it starts
to matter.

- **Q-1** — Tombstones: does a deleted Document keep its row forever, or is the row
  pruned after some grace period? Affects whether the CRUD list can show deletion history.
- **Q-2** — Should identical uploads (same `checksumSha256`) share one `StoredObject`?
  Cheap to add later; changes purge semantics, since bytes can then outlive one Document.
- **Q-3** — Is the retention window per-Document (chosen at upload) or global?
  Modelled as a relation so per-Document is possible without restructuring.
