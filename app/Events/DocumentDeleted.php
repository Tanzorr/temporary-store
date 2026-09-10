<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DeletionEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised after a deletion is durably committed (I-4), for TASK-007's
 * listener to turn into a queued RabbitMQ publish. Dispatched identically
 * for both DeletionTrigger cases — the publisher must not branch on trigger.
 */
final class DocumentDeleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly DeletionEvent $deletionEvent,
    ) {}
}
