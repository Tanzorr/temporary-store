<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | MessageQueue (RabbitMQ)
    |--------------------------------------------------------------------------
    |
    | The durable destination NotificationMessage is published to. This
    | application only publishes — see stack.md Boundaries.
    |
    */

    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),

    'exchange' => env('RABBITMQ_EXCHANGE', 'documents'),
    'queue' => env('RABBITMQ_QUEUE', 'document.deletions'),
    'routing_key' => env('RABBITMQ_ROUTING_KEY', 'document.deleted'),
];
