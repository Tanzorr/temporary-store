<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Domain\Document\DocumentStatus;
use App\Domain\Storage\StoredObject;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DocumentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_with_a_cast_status_and_timestamps(): void
    {
        $document = Document::factory()->create();

        $this->assertSame(DocumentStatus::Available, $document->status);
        $this->assertTrue($document->uploaded_at->isToday());
        $this->assertTrue($document->expires_at->isFuture());
    }

    #[Test]
    public function it_exposes_a_stored_object_value_object(): void
    {
        $document = Document::factory()->create([
            'disk' => 'local',
            'relative_path' => 'documents/foo.pdf',
        ]);

        $storedObject = $document->storedObject();

        $this->assertInstanceOf(StoredObject::class, $storedObject);
        $this->assertSame('local', $storedObject->disk);
        $this->assertSame('documents/foo.pdf', $storedObject->relativePath);
    }

    #[Test]
    public function it_produces_a_past_deadline_in_the_expired_state(): void
    {
        $document = Document::factory()->expired()->create();

        $this->assertTrue($document->expires_at->isPast());
    }

    #[Test]
    public function it_sets_status_to_deleted_in_the_deleted_state(): void
    {
        $document = Document::factory()->deleted()->create();

        $this->assertSame(DocumentStatus::Deleted, $document->status);
    }
}
