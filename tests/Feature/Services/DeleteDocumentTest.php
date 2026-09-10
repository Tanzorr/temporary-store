<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Domain\Deletion\DeletionTrigger;
use App\Domain\Document\DocumentStatus;
use App\Events\DocumentDeleted;
use App\Models\DeletionEvent;
use App\Models\Document;
use App\Services\DeleteDocument;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class DeleteDocumentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_a_document_and_publishes_an_event_after_commit(): void
    {
        Storage::fake('local');
        Event::fake([DocumentDeleted::class]);

        $document = Document::factory()->create();
        Storage::disk('local')->put($document->relative_path, 'contents');

        $event = app(DeleteDocument::class)->handle($document, DeletionTrigger::MANUAL_DELETION);

        $this->assertSame(1, DeletionEvent::query()->count());
        $this->assertSame(DeletionTrigger::MANUAL_DELETION, $event->trigger);
        $this->assertSame($document->id, $event->document_id);
        $this->assertSame('operator', $event->initiator);
        $this->assertNull($event->sweep_id);

        $document->refresh();
        $this->assertSame(DocumentStatus::Deleted, $document->status);
        Storage::disk('local')->assertMissing($document->relative_path);

        Event::assertDispatched(
            DocumentDeleted::class,
            fn (DocumentDeleted $dispatched): bool => $dispatched->deletionEvent->is($event)
        );
    }

    #[Test]
    public function it_records_the_sweep_id_and_scheduler_initiator_for_an_automatic_deletion(): void
    {
        Storage::fake('local');
        Event::fake([DocumentDeleted::class]);

        $document = Document::factory()->expired()->create();
        Storage::disk('local')->put($document->relative_path, 'contents');

        $event = app(DeleteDocument::class)->handle($document, DeletionTrigger::RETENTION_EXPIRY, 'sweep-abc');

        $this->assertSame(DeletionTrigger::RETENTION_EXPIRY, $event->trigger);
        $this->assertSame('scheduler', $event->initiator);
        $this->assertSame('sweep-abc', $event->sweep_id);
    }

    #[Test]
    public function it_rolls_back_and_publishes_nothing_when_the_purge_fails(): void
    {
        Event::fake([DocumentDeleted::class]);

        $document = Document::factory()->create();

        $failingDisk = $this->createMock(FilesystemContract::class);
        $failingDisk->method('delete')->willReturn(false);
        Storage::shouldReceive('disk')->with($document->disk)->andReturn($failingDisk);

        try {
            app(DeleteDocument::class)->handle($document, DeletionTrigger::MANUAL_DELETION);
            $this->fail('Expected a RuntimeException to be thrown.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, DeletionEvent::query()->count());
        $document->refresh();
        $this->assertSame(DocumentStatus::Available, $document->status);
        Event::assertNotDispatched(DocumentDeleted::class);
    }

    #[Test]
    public function it_is_a_no_op_returning_the_existing_event_on_a_second_delete(): void
    {
        Storage::fake('local');
        Event::fake([DocumentDeleted::class]);

        $document = Document::factory()->create();
        Storage::disk('local')->put($document->relative_path, 'contents');

        $service = app(DeleteDocument::class);
        $first = $service->handle($document, DeletionTrigger::MANUAL_DELETION);
        $second = $service->handle(Document::query()->findOrFail($document->id), DeletionTrigger::MANUAL_DELETION);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, DeletionEvent::query()->count());
        Event::assertDispatchedTimes(DocumentDeleted::class, 1);
    }
}
