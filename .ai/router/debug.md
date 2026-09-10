# Mode: DEBUG

Find the cause of an observed failure. Not a guess at a plausible cause — the
actual one.

## Load

Only what the symptom points at. Debugging with the whole context loaded buries
the signal.

1. The error output, in full — message, exception class, stack trace
2. The concept→code map in [`../context/architecture.md`](../context/architecture.md)
3. [`../context/tools.md`](../context/tools.md) for inspection commands

## Method

1. **State the symptom precisely.** What was expected, what happened, where
   observed. "Notifications aren't working" is not a symptom; "no message on
   `document.deletions` after a manual delete, HTTP 204 returned" is.
2. **Reproduce it.** A failing test is the ideal reproduction because it survives
   the fix and becomes a regression guard.
3. **Bisect the path.** For the deletion path: did the `DeletionEvent` row appear?
   Was the job dispatched? Did the job run? Did the publisher connect? Each
   question isolates one segment.
4. **Read the code on that segment.** Do not pattern-match to a similar bug you
   have seen; the framework version and config here are specific.
5. **Form one hypothesis, then test it.** If the test contradicts it, discard it
   fully rather than patching it into something more complicated.
6. **Fix the cause.** A workaround that hides the symptom leaves the bug and
   removes the evidence.

## Frequent Causes Here

| Symptom | Look first at |
| :--- | :--- |
| No notification after deletion | Is the `queue` container running? `docker compose ps`, then `logs queue`. Was the job dispatched inside a transaction that rolled back? |
| Files never auto-deleted | Is `scheduler` running? `php artisan schedule:list`. Is `expires_at` set at all? |
| Config change has no effect | `config:cache` is stale, or `env()` is being called outside `config/*.php` |
| Upload rejected unexpectedly | PHP `upload_max_filesize` / `post_max_size` are below `UPLOAD_MAX_SIZE_BYTES`; the server refuses before Laravel sees it |
| Duplicate notifications | Sweep is not idempotent (I-6), or a retry is not using the event id as message id (I-5) |
| Test passes alone, fails in suite | Shared state between tests, or `Carbon::setTestNow()` not reset |

## Rules

- **Do not change more than one thing at a time.** Two simultaneous changes make
  a passing result uninformative.
- **Do not "fix" by retrying differently until it works.** If you cannot say why
  the fix works, you have not found the cause.
- **Keep the reproduction.** Add it to the suite before closing.
- **Report honestly**, including what you tried that did not work. That is data,
  and it stops the next person repeating it.
