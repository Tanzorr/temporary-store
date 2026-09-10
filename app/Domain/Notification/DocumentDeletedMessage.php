<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Models\DeletionEvent;

/**
 * The NotificationMessage payload for one DeletionEvent (Message Contract,
 * TASK-007). Built once from the event and its (tombstoned) Document — no
 * branch on DeletionTrigger, so a manual and an automatic deletion produce
 * an identical shape (AC-10).
 */
final class DocumentDeletedMessage
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public readonly string $messageId,
        private readonly array $payload,
    ) {}

    public static function fromDeletionEvent(DeletionEvent $deletionEvent): self
    {
        $document = $deletionEvent->document;

        $payload = [
            'schema_version' => 1,
            'event_id' => $deletionEvent->uuid,
            'occurred_at' => $deletionEvent->occurred_at->clone()->utc()->toIso8601String(),
            'trigger' => $deletionEvent->trigger->value,
            'recipient' => (string) config('notifications.recipient_email'),
            'subject' => "Document deleted: {$document->original_name}",
            'document' => [
                'uuid' => $document->uuid,
                'original_name' => $document->original_name,
                'size_bytes' => $document->size_bytes,
                'mime_type' => $document->mime_type,
                'uploaded_at' => $document->uploaded_at->clone()->utc()->toIso8601String(),
                'expires_at' => $document->expires_at->clone()->utc()->toIso8601String(),
            ],
        ];

        return new self(messageId: $deletionEvent->uuid, payload: $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    public function toJson(): string
    {
        return json_encode($this->payload, JSON_THROW_ON_ERROR);
    }
}
