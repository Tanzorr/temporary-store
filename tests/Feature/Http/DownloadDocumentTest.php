<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DownloadDocumentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_streams_the_file_under_its_original_name_with_the_stored_mime_type(): void
    {
        Storage::fake('local');

        $document = Document::factory()->create([
            'original_name' => 'my "report".pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk('local')->put($document->relative_path, 'file contents');

        $response = $this->get("/documents/{$document->uuid}/download");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);

        // I-7: the original name is quoted with its own quotes escaped —
        // Symfony's makeDisposition(), not a hand-built header.
        $expectedFilename = str_replace('"', '\\"', $document->original_name);
        $this->assertStringContainsString("filename=\"{$expectedFilename}\"", $disposition);
    }

    #[Test]
    public function it_returns_404_for_an_unknown_uuid(): void
    {
        $response = $this->get('/documents/'.Str::uuid().'/download');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_an_expired_document_the_sweep_has_not_reached_yet(): void
    {
        Storage::fake('local');

        $document = Document::factory()->expired()->create();
        Storage::disk('local')->put($document->relative_path, 'file contents');

        $response = $this->get("/documents/{$document->uuid}/download");

        // Still `available` — the sweep has not run. Past its retention
        // deadline is enough to stop serving it (ADR-015).
        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_a_deleted_document(): void
    {
        $document = Document::factory()->deleted()->create();

        $response = $this->get("/documents/{$document->uuid}/download");

        $response->assertNotFound();
    }
}
