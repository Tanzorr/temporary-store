<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Notification\DocumentDeletedMessage;
use App\Domain\Notification\NotificationPublisher;
use RuntimeException;

/**
 * Test double for NotificationPublisher (ADR-004, testing.md -> Fakes).
 * Records every message it actually publishes; can be told to fail like a
 * broker outage on its next N calls so retry behaviour (I-10, T-11) is
 * provable without a real connection.
 */
final class RecordingNotificationPublisher implements NotificationPublisher
{
    /** @var list<DocumentDeletedMessage> */
    public array $published = [];

    private int $failuresRemaining = 0;

    public function failNextPublish(int $times = 1): void
    {
        $this->failuresRemaining = $times;
    }

    public function publish(DocumentDeletedMessage $message): void
    {
        if ($this->failuresRemaining > 0) {
            $this->failuresRemaining--;

            throw new RuntimeException('Simulated broker outage.');
        }

        $this->published[] = $message;
    }
}
