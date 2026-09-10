<?php

declare(strict_types=1);

namespace App\Infrastructure\Rabbit;

use App\Domain\Notification\DocumentDeletedMessage;
use App\Domain\Notification\NotificationPublisher;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * The only class in this codebase that imports PhpAmqpLib\* (AC-1, ADR-004).
 * Opens one connection per publish so a stale connection from a previous,
 * failed attempt is never retried (I-10) — the queued job resolves a fresh
 * instance on every attempt.
 */
final class RabbitPublisher implements NotificationPublisher
{
    public function publish(DocumentDeletedMessage $message): void
    {
        $connection = new AMQPStreamConnection(
            (string) config('rabbitmq.host'),
            (int) config('rabbitmq.port'),
            (string) config('rabbitmq.user'),
            (string) config('rabbitmq.password'),
        );

        try {
            $channel = $connection->channel();

            $exchange = (string) config('rabbitmq.exchange');
            $queue = (string) config('rabbitmq.queue');
            $routingKey = (string) config('rabbitmq.routing_key');

            $channel->exchange_declare($exchange, 'direct', false, true, false);
            $channel->queue_declare($queue, false, true, false, false);
            $channel->queue_bind($queue, $exchange, $routingKey);

            $channel->basic_publish(
                new AMQPMessage($message->toJson(), [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'message_id' => $message->messageId,
                ]),
                $exchange,
                $routingKey,
            );

            $channel->close();
        } finally {
            $connection->close();
        }
    }
}
