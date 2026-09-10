<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Deletion\DeletionTrigger;
use App\Domain\Document\DocumentStatus;
use App\Events\DocumentDeleted;
use App\Models\DeletionEvent;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The only place a Document may be deleted (I-3). Both the manual delete
 * route (TASK-005) and the retention sweep (TASK-008) call this.
 */
final class DeleteDocument
{
    public function handle(Document $document, DeletionTrigger $trigger, ?string $sweepId = null): DeletionEvent
    {
        $existing = DeletionEvent::query()->where('document_id', $document->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($document, $trigger, $sweepId): DeletionEvent {
            $deletionEvent = DeletionEvent::create([
                'uuid' => (string) Str::uuid(),
                'document_id' => $document->id,
                'trigger' => $trigger,
                'initiator' => $this->initiatorFor($trigger),
                'occurred_at' => CarbonImmutable::now(),
                'sweep_id' => $sweepId,
            ]);

            $storedObject = $document->storedObject();
            $purged = Storage::disk($storedObject->disk)->delete($storedObject->relativePath);

            if (! $purged) {
                throw new RuntimeException(
                    "Failed to purge stored object for document [{$document->uuid}] on disk [{$storedObject->disk}]."
                );
            }

            $document->update(['status' => DocumentStatus::Deleted]);

            DB::afterCommit(function () use ($document, $deletionEvent, $trigger): void {
                Log::info('Document deleted', [
                    'document_uuid' => $document->uuid,
                    'deletion_event_uuid' => $deletionEvent->uuid,
                    'trigger' => $trigger->value,
                ]);

                event(new DocumentDeleted($deletionEvent));
            });

            return $deletionEvent;
        });
    }

    private function initiatorFor(DeletionTrigger $trigger): string
    {
        return match ($trigger) {
            DeletionTrigger::MANUAL_DELETION => 'operator',
            DeletionTrigger::RETENTION_EXPIRY => 'scheduler',
        };
    }
}
