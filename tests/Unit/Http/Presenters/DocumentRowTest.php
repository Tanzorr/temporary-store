<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Presenters;

use App\Http\Presenters\DocumentRow;
use App\Models\Document;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DocumentRowTest extends TestCase
{
    #[Test]
    public function it_formats_size_type_and_timestamps(): void
    {
        $now = CarbonImmutable::parse('2026-09-10 12:00:00');
        $document = $this->documentWith([
            'original_name' => 'report.pdf',
            'extension' => 'pdf',
            'size_bytes' => 2 * 1024 * 1024,
            'uploaded_at' => CarbonImmutable::parse('2026-09-09 08:00:00'),
            'expires_at' => CarbonImmutable::parse('2026-09-10 08:00:00'),
        ]);

        $row = DocumentRow::from($document, $now);

        $this->assertSame('report.pdf', $row->originalName);
        $this->assertSame('2.0 MB', $row->sizeLabel);
        $this->assertSame('PDF', $row->typeLabel);
        $this->assertSame('2026-09-09 08:00 UTC', $row->uploadedAtLabel);
        $this->assertSame('2026-09-10 08:00 UTC', $row->expiresAtLabel);
    }

    #[Test]
    public function it_labels_a_small_file_in_kilobytes(): void
    {
        $document = $this->documentWith(['size_bytes' => 512]);

        $row = DocumentRow::from($document, CarbonImmutable::now());

        $this->assertSame('0.5 KB', $row->sizeLabel);
    }

    #[Test]
    public function it_reports_awaiting_sweep_when_the_deadline_has_passed(): void
    {
        $now = CarbonImmutable::parse('2026-09-10 12:00:00');
        $document = $this->documentWith(['expires_at' => $now->subMinute()]);

        $row = DocumentRow::from($document, $now);

        $this->assertTrue($row->awaitingSweep);
        $this->assertSame('awaiting sweep', $row->timeRemainingLabel);
    }

    #[Test]
    public function it_reports_awaiting_sweep_exactly_on_the_deadline(): void
    {
        $now = CarbonImmutable::parse('2026-09-10 12:00:00');
        $document = $this->documentWith(['expires_at' => $now]);

        $row = DocumentRow::from($document, $now);

        $this->assertTrue($row->awaitingSweep);
        $this->assertSame('awaiting sweep', $row->timeRemainingLabel);
    }

    #[Test]
    public function it_renders_hours_and_minutes_remaining(): void
    {
        $now = CarbonImmutable::parse('2026-09-10 00:00:00');
        $document = $this->documentWith(['expires_at' => $now->addHours(23)->addMinutes(58)]);

        $row = DocumentRow::from($document, $now);

        $this->assertFalse($row->awaitingSweep);
        $this->assertSame('23h 58m', $row->timeRemainingLabel);
    }

    #[Test]
    public function it_renders_minutes_only_under_an_hour(): void
    {
        $now = CarbonImmutable::parse('2026-09-10 00:00:00');
        $document = $this->documentWith(['expires_at' => $now->addMinutes(12)]);

        $row = DocumentRow::from($document, $now);

        $this->assertSame('12m', $row->timeRemainingLabel);
    }

    #[Test]
    public function it_renders_under_one_minute_remaining(): void
    {
        $now = CarbonImmutable::parse('2026-09-10 00:00:00');
        $document = $this->documentWith(['expires_at' => $now->addSeconds(30)]);

        $row = DocumentRow::from($document, $now);

        $this->assertSame('<1m', $row->timeRemainingLabel);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function documentWith(array $overrides): Document
    {
        return new Document(array_merge([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'original_name' => 'report.pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'uploaded_at' => CarbonImmutable::now(),
            'expires_at' => CarbonImmutable::now()->addDay(),
        ], $overrides));
    }
}
