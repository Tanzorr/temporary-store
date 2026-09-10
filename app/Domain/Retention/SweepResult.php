<?php

declare(strict_types=1);

namespace App\Domain\Retention;

use Carbon\CarbonImmutable;

/**
 * The outcome of one RetentionSweep. Not persisted — this is the shape of
 * what the sweep logs and prints, per architecture.md's concept map.
 */
final readonly class SweepResult
{
    public function __construct(
        public string $sweepId,
        public CarbonImmutable $ranAt,
        public int $candidateCount,
        public int $deletedCount,
    ) {}
}
