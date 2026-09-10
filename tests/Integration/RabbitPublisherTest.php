<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Deletion\DeletionTrigger;
use App\Domain\Notification\DocumentDeletedMessage;
use App\Infrastructure\Rabbit\RabbitPublisher;
use App\Models\DeletionEvent;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Publishes against the real broker (docker-compose's rabbitmq service) and
 * reads the message back, the way a consumer would. Catches a misdeclared
 * exchange or queue that a fake NotificationPublisher cannot (AC-8).
 * Skipped when the broker is unreachable, so `php artisan test` still
 * passes on a machine with no RabbitMQ.
 */
final class RabbitPublisherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $socket = @fsockopen(
            (string) config('rabbitmq.host'),
            (int) config('rabbitmq.port'),
            $errno,
            $errstr,
            1,
        );

        if ($socket === false) {
            $this->markTestSkipped('RabbitMQ is not reachable — skipping the integration test.');
        }

        fclose($socket);
    }

    #[Test]
    public function it_publishes_a_durable_persistent_message_a_consumer_can_read_back(): void
    {
        $document = new Document([
            'uuid' => (string) Str::uuid(),
            'original_name' => 'integration.pdf',
            'size_bytes' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_at' => CarbonImmutable::now(),
            'expires_at' => CarbonImmutable::now()->addDay(),
        ]);

        $deletionEvent = new DeletionEvent([
            'uuid' => (string) Str::uuid(),
            'trigger' => DeletionTrigger::MANUAL_DELETION,
            'occurred_at' => CarbonImmutable::now(),
        ]);
        $deletionEvent->setRelation('document', $document);

        $message = DocumentDeletedMessage::fromDeletionEvent($deletionEvent);

        $connection = new AMQPStreamConnection(
            (string) config('rabbitmq.host'),
            (int) config('rabbitmq.port'),
            (string) config('rabbitmq.user'),
            (string) config('rabbitmq.password'),
        );
        $channel = $connection->channel();

        $exchange = (string) config('rabbitmq.exchange');
        $queue = (string) config('rabbitmq.queue');
        $routingKey = (string) config('rabbitmq.routing_key');

        // Assert durability directly: declaring against an already-durable
        // exchange/queue is idempotent, but a non-durable redeclaration
        // would raise a channel exception (PRECONDITION_FAILED).
        $channel->exchange_declare($exchange, 'direct', false, true, false);
        $channel->queue_declare($queue, false, true, false, false);
        $channel->queue_bind($queue, $exchange, $routingKey);
        $channel->queue_purge($queue);

        app(RabbitPublisher::class)->publish($message);

        $received = $channel->basic_get($queue);
        $this->assertNotNull($received, 'Expected to read the published message back from the queue.');
        $channel->basic_ack($received->getDeliveryTag());

        $this->assertSame($message->messageId, $received->get('message_id'));
        $this->assertSame('application/json', $received->get('content_type'));
        $this->assertSame(2, $received->get('delivery_mode'));

        $body = json_decode($received->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($deletionEvent->uuid, $body['event_id']);
        $this->assertSame('manual_deletion', $body['trigger']);

        $channel->close();
        $connection->close();
    }
}
