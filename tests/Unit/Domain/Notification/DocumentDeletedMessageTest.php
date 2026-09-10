<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Notification;

use App\Domain\Deletion\DeletionTrigger;
use App\Domain\Notification\DocumentDeletedMessage;
use App\Models\DeletionEvent;
use App\Models\Document;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DocumentDeletedMessageTest extends TestCase
{
    #[Test]
    public function it_serialises_the_message_contract_from_a_deletion_event(): void
    {
        config(['notifications.recipient_email' => 'ops@example.com']);

        $document = new Document([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'original_name' => 'contract.pdf',
            'size_bytes' => 2048,
            'mime_type' => 'application/pdf',
            'uploaded_at' => CarbonImmutable::parse('2026-09-10T08:00:00Z'),
            'expires_at' => CarbonImmutable::parse('2026-09-11T08:00:00Z'),
        ]);

        $deletionEvent = new DeletionEvent([
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'trigger' => DeletionTrigger::MANUAL_DELETION,
            'occurred_at' => CarbonImmutable::parse('2026-09-10T12:00:00Z'),
        ]);
        $deletionEvent->setRelation('document', $document);

        $message = DocumentDeletedMessage::fromDeletionEvent($deletionEvent);

        $this->assertSame('22222222-2222-2222-2222-222222222222', $message->messageId);
        $this->assertSame([
            'schema_version' => 1,
            'event_id' => '22222222-2222-2222-2222-222222222222',
            'occurred_at' => '2026-09-10T12:00:00+00:00',
            'trigger' => 'manual_deletion',
            'recipient' => 'ops@example.com',
            'subject' => 'Document deleted: contract.pdf',
            'document' => [
                'uuid' => '11111111-1111-1111-1111-111111111111',
                'original_name' => 'contract.pdf',
                'size_bytes' => 2048,
                'mime_type' => 'application/pdf',
                'uploaded_at' => '2026-09-10T08:00:00+00:00',
                'expires_at' => '2026-09-11T08:00:00+00:00',
            ],
        ], $message->toArray());
    }

    #[Test]
    public function it_carries_the_retention_expiry_trigger_without_branching(): void
    {
        $document = new Document([
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'original_name' => 'report.docx',
            'size_bytes' => 4096,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'uploaded_at' => CarbonImmutable::parse('2026-09-08T08:00:00Z'),
            'expires_at' => CarbonImmutable::parse('2026-09-09T08:00:00Z'),
        ]);

        $deletionEvent = new DeletionEvent([
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'trigger' => DeletionTrigger::RETENTION_EXPIRY,
            'occurred_at' => CarbonImmutable::parse('2026-09-09T08:00:01Z'),
        ]);
        $deletionEvent->setRelation('document', $document);

        $payload = DocumentDeletedMessage::fromDeletionEvent($deletionEvent)->toArray();

        $this->assertSame('retention_expiry', $payload['trigger']);
    }
}
