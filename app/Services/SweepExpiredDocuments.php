<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Deletion\DeletionTrigger;
use App\Domain\Retention\SweepResult;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * One execution of the RetentionSweep (I-6). Selects expired Documents and
 * hands each to DeleteDocument — the only place a Document may be deleted
 * (I-3). No deletion logic of its own.
 */
final class SweepExpiredDocuments
{
    private const CHUNK_SIZE = 200;

    public function __construct(
        private readonly DeleteDocument $deleteDocument,
    ) {}

    public function handle(): SweepResult
    {
        $sweepId = (string) Str::uuid();
        $candidateCount = 0;
        $deletedCount = 0;

        Document::query()
            ->available()
            ->where('expires_at', '<=', CarbonImmutable::now())
            ->chunkById(self::CHUNK_SIZE, function (Collection $documents) use ($sweepId, &$candidateCount, &$deletedCount): void {
                /** @var Document $document */
                foreach ($documents as $document) {
                    $candidateCount++;

                    try {
                        $this->deleteDocument->handle($document, DeletionTrigger::RETENTION_EXPIRY, $sweepId);
                        $deletedCount++;
                    } catch (Throwable $exception) {
                        Log::error('Retention sweep failed to delete a document', [
                            'sweep_id' => $sweepId,
                            'document_uuid' => $document->uuid,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $result = new SweepResult($sweepId, CarbonImmutable::now(), $candidateCount, $deletedCount);

        Log::info('Retention sweep completed', [
            'sweep_id' => $result->sweepId,
            'ran_at' => $result->ranAt->toIso8601String(),
            'candidate_count' => $result->candidateCount,
            'deleted_count' => $result->deletedCount,
        ]);

        return $result;
    }
}
