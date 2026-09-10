<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domain\Deletion\DeletionTrigger;
use App\Domain\Notification\DocumentDeletedMessage;
use App\Domain\Notification\NotificationPublisher;
use App\Events\DocumentDeleted;
use App\Jobs\PublishDeletionNotification;
use App\Models\DeletionEvent;
use App\Models\Document;
use App\Services\DeleteDocument;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\RecordingNotificationPublisher;
use Tests\TestCase;

/**
 * QUEUE_CONNECTION=sync (phpunit.xml) makes a dispatched job run inline, so
 * these tests exercise the real chain: DeleteDocument -> DocumentDeleted ->
 * DispatchDeletionNotification -> PublishDeletionNotification -> the bound
 * fake NotificationPublisher. Nothing here calls the listener directly.
 */
final class PublishDeletionNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_publishes_a_message_when_a_document_is_deleted_manually(): void
    {
        Storage::fake('local');
        $fake = new RecordingNotificationPublisher;
        $this->app->instance(NotificationPublisher::class, $fake);

        $document = Document::factory()->create();
        Storage::disk('local')->put($document->relative_path, 'contents');

        $event = app(DeleteDocument::class)->handle($document, DeletionTrigger::MANUAL_DELETION);

        $this->assertCount(1, $fake->published);
        $payload = $fake->published[0]->toArray();
        $this->assertSame('manual_deletion', $payload['trigger']);
        $this->assertSame($event->uuid, $fake->published[0]->messageId);
        $this->assertSame($event->uuid, $payload['event_id']);
    }

    #[Test]
    public function it_publishes_a_message_when_a_document_is_swept_as_expired(): void
    {
        Storage::fake('local');
        $fake = new RecordingNotificationPublisher;
        $this->app->instance(NotificationPublisher::class, $fake);

        $document = Document::factory()->expired()->create();
        Storage::disk('local')->put($document->relative_path, 'contents');

        app(DeleteDocument::class)->handle($document, DeletionTrigger::RETENTION_EXPIRY, 'sweep-abc');

        $this->assertCount(1, $fake->published);
        $this->assertSame('retention_expiry', $fake->published[0]->toArray()['trigger']);
    }

    #[Test]
    public function it_publishes_one_message_per_trigger_differing_only_in_identity_and_trigger(): void
    {
        Storage::fake('local');
        $fake = new RecordingNotificationPublisher;
        $this->app->instance(NotificationPublisher::class, $fake);

        $shared = [
            'original_name' => 'shared.pdf',
            'size_bytes' => 4096,
            'mime_type' => 'application/pdf',
        ];
        $manualDocument = Document::factory()->create($shared);
        $sweptDocument = Document::factory()->create($shared);
        Storage::disk('local')->put($manualDocument->relative_path, 'a');
        Storage::disk('local')->put($sweptDocument->relative_path, 'b');

        $service = app(DeleteDocument::class);
        $service->handle($manualDocument, DeletionTrigger::MANUAL_DELETION);
        $service->handle($sweptDocument, DeletionTrigger::RETENTION_EXPIRY, 'sweep-xyz');

        $this->assertCount(2, $fake->published);

        $payloads = array_map(
            static fn (DocumentDeletedMessage $message): array => $message->toArray(),
            $fake->published,
        );

        $triggers = array_column($payloads, 'trigger');
        sort($triggers);
        $this->assertSame(['manual_deletion', 'retention_expiry'], $triggers);

        $normalised = array_map(static function (array $payload): array {
            unset($payload['event_id'], $payload['occurred_at'], $payload['trigger'], $payload['document']['uuid']);

            return $payload;
        }, $payloads);
        $this->assertSame($normalised[0], $normalised[1]);
    }

    #[Test]
    public function it_publishes_nothing_when_the_deletion_rolls_back(): void
    {
        $fake = new RecordingNotificationPublisher;
        $this->app->instance(NotificationPublisher::class, $fake);

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

        $this->assertSame([], $fake->published);
    }

    #[Test]
    public function it_retries_after_a_publish_failure_without_duplicating_the_event(): void
    {
        Storage::fake('local');
        // The initial deletion must succeed cleanly, independent of the
        // publisher under test: QUEUE_CONNECTION=sync means a failing job
        // would throw at the dispatch site instead of being retried by a
        // worker, so the event chain is faked here and the job is driven
        // by hand below, exactly as a worker retries it.
        Event::fake([DocumentDeleted::class]);

        $document = Document::factory()->create();
        Storage::disk('local')->put($document->relative_path, 'contents');

        $event = app(DeleteDocument::class)->handle($document, DeletionTrigger::MANUAL_DELETION);

        $fake = new RecordingNotificationPublisher;
        $fake->failNextPublish();
        $job = new PublishDeletionNotification($event);

        try {
            $job->handle($fake);
            $this->fail('Expected the first attempt to fail like a broker outage.');
        } catch (RuntimeException) {
            // expected — first worker attempt hits the down broker
        }

        $job->handle($fake);

        $this->assertCount(1, $fake->published);
        $this->assertSame($event->uuid, $fake->published[0]->messageId);
        $this->assertSame(1, DeletionEvent::query()->count());
    }
}
