<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Retention TTL
    |--------------------------------------------------------------------------
    |
    | How many hours a Document may live before the sweep collects it.
    | Read by App\Domain\Retention\RetentionPolicy — nowhere else computes
    | an expiry (invariant I-2).
    |
    */

    'ttl_hours' => (int) env('RETENTION_TTL_HOURS', 24),
];
