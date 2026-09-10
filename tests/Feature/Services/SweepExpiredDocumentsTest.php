<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Domain\Document\DocumentStatus;
use App\Domain\Notification\NotificationPublisher;
use App\Models\DeletionEvent;
use App\Models\Document;
use App\Services\SweepExpiredDocuments;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\RecordingNotificationPublisher;
use Tests\TestCase;

final class SweepExpiredDocumentsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_an_expired_document_and_publishes_exactly_one_message(): void
    {
        Storage::fake('local');
        $fake = new RecordingNotificationPublisher;
        $this->app->instance(NotificationPublisher::class, $fake);

        $document = Document::factory()->expired()->create();
        Storage::disk('local')->put($document->relative_path, 'contents');

        $result = app(SweepExpiredDocuments::class)->handle();

        $this->assertSame(1, $result->candidateCount);
        $this->assertSame(1, $result->deletedCount);

        $event = DeletionEvent::query()->sole();
        $this->assertSame('retention_expiry', $event->trigger->value);
        $this->assertSame($result->sweepId, $event->sweep_id);

        $document->refresh();
        $this->assertSame(DocumentStatus::Deleted, $document->status);

        $this->assertCount(1, $fake->published);
        $this->assertSame('retention_expiry', $fake->published[0]->toArray()['trigger']);
    }

    #[Test]
    public function it_does_not_touch_a_document_that_has_not_expired_yet(): void
    {
        Storage::fake('local');
        $fake = new RecordingNotificationPublisher;
        $this->app->instance(NotificationPublisher::class, $fake);

        $document = Document::factory()->create();

        $result = app(SweepExpiredDocuments::class)->handle();

        $this->assertSame(0, $result->candidateCount);
        $this->assertSame(0, $result->deletedCount);
        $this->assertSame(0, DeletionEvent::query()->count());

        $document->refresh();
        $this->assertSame(DocumentStatus::Available, $document->status);
        $this->assertSame([], $fake->published);
    }

    #[Test]
    public function running_the_sweep_twice_deletes_nothing_twice_and_publishes_no_duplicate(): void
    {
        Storage::fake('local');
        $fake = new RecordingNotificationPublisher;
        $this->app->instance(NotificationPublisher::class, $fake);

        $document = Document::factory()->expired()->create();
        Storage::disk('local')->put($document->relative_path, 'contents');

        $service = app(SweepExpiredDocuments::class);
        $first = $service->handle();
        $second = $service->handle();

        $this->assertSame(1, $first->candidateCount);
        $this->assertSame(1, $first->deletedCount);
        // The document is no longer `available`, so the second run's own
        // query excludes it — the first line of defence. DeleteDocument's
        // idempotency (I-6) is the second, in case a candidate is ever
        // re-selected (e.g. a race with another sweep).
        $this->assertSame(0, $second->candidateCount);
        $this->assertSame(0, $second->deletedCount);
        $this->assertSame(1, DeletionEvent::query()->count());
        $this->assertCount(1, $fake->published);
        $this->assertNotSame($first->sweepId, $second->sweepId);
    }

    #[Test]
    public function one_document_failing_to_purge_does_not_abort_the_rest_of_the_run(): void
    {
        $fake = new RecordingNotificationPublisher;
        $this->app->instance(NotificationPublisher::class, $fake);

        $bad = Document::factory()->expired()->create();
        $good = Document::factory()->expired()->create();

        $partiallyFailingDisk = $this->createMock(FilesystemContract::class);
        $partiallyFailingDisk->method('delete')->willReturnCallback(
            fn (string $path): bool => $path !== $bad->relative_path
        );
        Storage::shouldReceive('disk')->with('local')->andReturn($partiallyFailingDisk);

        $result = app(SweepExpiredDocuments::class)->handle();

        $this->assertSame(2, $result->candidateCount);
        $this->assertSame(1, $result->deletedCount);

        $bad->refresh();
        $good->refresh();
        $this->assertSame(DocumentStatus::Available, $bad->status);
        $this->assertSame(DocumentStatus::Deleted, $good->status);
        $this->assertCount(1, $fake->published);
    }
}
