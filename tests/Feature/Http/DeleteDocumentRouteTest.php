<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Domain\Deletion\DeletionTrigger;
use App\Domain\Document\DocumentStatus;
use App\Models\DeletionEvent;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DeleteDocumentRouteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_through_delete_document_producing_exactly_one_manual_deletion_event(): void
    {
        Storage::fake('local');

        $document = Document::factory()->create();
        Storage::disk('local')->put($document->relative_path, 'contents');

        $response = $this->delete("/documents/{$document->uuid}");

        $response->assertOk();
        $response->assertExactJson(['uuid' => $document->uuid]);

        $this->assertSame(1, DeletionEvent::query()->count());
        $event = DeletionEvent::query()->sole();
        $this->assertSame(DeletionTrigger::MANUAL_DELETION, $event->trigger);
        $this->assertSame($document->id, $event->document_id);

        $document->refresh();
        $this->assertSame(DocumentStatus::Deleted, $document->status);
    }

    #[Test]
    public function it_still_deletes_an_expired_document_the_sweep_has_not_reached_yet(): void
    {
        Storage::fake('local');

        // Narrowing the download path to `downloadable` must not narrow this
        // one: deleting early is the operator's job (V-5), and the result is
        // one DeletionEvent either way (I-3).
        $document = Document::factory()->expired()->create();
        Storage::disk('local')->put($document->relative_path, 'contents');

        $response = $this->delete("/documents/{$document->uuid}");

        $response->assertOk();
        $this->assertSame(1, DeletionEvent::query()->count());
        $this->assertSame(DeletionTrigger::MANUAL_DELETION, DeletionEvent::query()->sole()->trigger);
    }

    #[Test]
    public function it_returns_404_for_an_unknown_uuid(): void
    {
        $response = $this->delete('/documents/'.Str::uuid());

        $response->assertNotFound();
        $this->assertSame(0, DeletionEvent::query()->count());
    }

    #[Test]
    public function it_returns_404_for_an_already_deleted_document_not_a_second_event(): void
    {
        $document = Document::factory()->deleted()->create();

        $response = $this->delete("/documents/{$document->uuid}");

        $response->assertNotFound();
        $this->assertSame(0, DeletionEvent::query()->count());
    }
}
