<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SweepExpiredDocuments as SweepExpiredDocumentsService;
use Illuminate\Console\Command;

/**
 * Thin entry point (architecture.md -> Layering): delegates to the service
 * and reports its SweepResult. Safe to run by hand at any time (AC-10),
 * which is how the retention path gets verified without waiting 24h.
 */
final class SweepExpiredDocuments extends Command
{
    protected $signature = 'documents:sweep-expired';

    protected $description = 'Delete every available Document past its retention deadline and notify on each.';

    public function handle(SweepExpiredDocumentsService $sweep): int
    {
        $result = $sweep->handle();

        $this->info(sprintf(
            'Sweep %s: %d/%d candidate(s) deleted.',
            $result->sweepId,
            $result->deletedCount,
            $result->candidateCount,
        ));

        return self::SUCCESS;
    }
}
