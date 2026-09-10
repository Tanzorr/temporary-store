<?php

declare(strict_types=1);

namespace App\Domain\Retention;

use Carbon\CarbonImmutable;

/**
 * Single source of truth for expiry arithmetic (invariant I-2). No other
 * code may compute a Document's expiry.
 */
final class RetentionPolicy
{
    public function deadlineFor(CarbonImmutable $uploadedAt): CarbonImmutable
    {
        return $uploadedAt->addHours($this->ttlHours());
    }

    private function ttlHours(): int
    {
        return (int) config('retention.ttl_hours');
    }
}
