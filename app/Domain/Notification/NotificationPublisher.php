<?php

declare(strict_types=1);

namespace App\Domain\Notification;

/**
 * Carries a DocumentDeletedMessage onward to the MessageQueue (ADR-004).
 * The domain names no AMQP class; App\Infrastructure\Rabbit\RabbitPublisher
 * is the only implementation.
 */
interface NotificationPublisher
{
    public function publish(DocumentDeletedMessage $message): void;
}
