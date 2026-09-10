<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Notification\DocumentDeletedMessage;
use App\Domain\Notification\NotificationPublisher;
use App\Models\DeletionEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Publishes the NotificationMessage for one DeletionEvent. Retried from
 * durable state on a broker outage (I-10) — does nothing else, so
 * queue:failed and the logs name this class, not a listener wrapper
 * (ADR-014).
 */
final class PublishDeletionNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly DeletionEvent $deletionEvent,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(NotificationPublisher $publisher): void
    {
        $publisher->publish(DocumentDeletedMessage::fromDeletionEvent($this->deletionEvent));
    }
}
