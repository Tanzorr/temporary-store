<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DocumentDeleted;
use App\Jobs\PublishDeletionNotification;

/**
 * Translates DocumentDeleted into a queued publish. Deliberately not
 * ShouldQueue itself (ADR-014) — the retry policy belongs to the job, not
 * to this two-line wiring. Auto-discovered from the typed $event parameter.
 */
final class DispatchDeletionNotification
{
    public function handle(DocumentDeleted $event): void
    {
        PublishDeletionNotification::dispatch($event->deletionEvent);
    }
}
