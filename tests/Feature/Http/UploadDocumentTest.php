<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Domain\Retention\RetentionPolicy;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UploadDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Hermetic against whatever UPLOAD_DISK/UPLOAD_MAX_SIZE_BYTES the real .env carries.
        config(['uploads.disk' => 'local']);
        Storage::fake('local');
    }

    #[Test]
    public function it_accepts_a_valid_pdf_upload(): void
    {
        $response = $this->post('/documents', [
            'file' => $this->fixture('sample.pdf', 'report.pdf', 'application/pdf'),
        ]);

        $response->assertCreated();
        $this->assertSame(
            ['uuid', 'original_name', 'size_bytes', 'mime_type', 'uploaded_at', 'expires_at'],
            array_keys($response->json())
        );

        $document = Document::query()->where('uuid', $response->json('uuid'))->firstOrFail();

        $this->assertSame('report.pdf', $document->original_name);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame('pdf', $document->extension);
        $this->assertTrue(
            $document->expires_at->equalTo(
                app(RetentionPolicy::class)->deadlineFor($document->uploaded_at->toImmutable())
            )
        );
        Storage::disk('local')->assertExists($document->relative_path);
    }

    #[Test]
    public function it_accepts_a_valid_docx_upload(): void
    {
        $response = $this->post('/documents', [
            'file' => $this->fixture(
                'sample.docx',
                'contract.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ),
        ]);

        $response->assertCreated();

        $document = Document::query()->where('uuid', $response->json('uuid'))->firstOrFail();

        $this->assertSame('docx', $document->extension);
        Storage::disk('local')->assertExists($document->relative_path);
    }

    #[Test]
    public function it_refuses_an_oversized_upload_and_leaves_no_bytes_or_row(): void
    {
        config(['uploads.max_size_bytes' => 1024]);

        $response = $this->post('/documents', [
            'file' => UploadedFile::fake()->create('big.pdf', 2),
        ]);

        $response->assertStatus(422);
        $response->assertExactJson(['code' => 'too_large']);
        $this->assertSame(0, Document::query()->count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    #[Test]
    public function it_refuses_a_disallowed_type_detected_by_content(): void
    {
        $response = $this->post('/documents', [
            'file' => $this->fixture('disallowed-content.pdf', 'invoice.pdf', 'application/pdf'),
        ]);

        $response->assertStatus(422);
        $response->assertExactJson(['code' => 'unsupported_type']);
        $this->assertSame(0, Document::query()->count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    #[Test]
    public function it_does_not_let_a_traversal_filename_escape_the_storage_disk(): void
    {
        $response = $this->post('/documents', [
            'file' => $this->fixture('sample.pdf', '../../etc/passwd.pdf', 'application/pdf'),
        ]);

        $response->assertCreated();

        $document = Document::query()->where('uuid', $response->json('uuid'))->firstOrFail();

        // Symfony's UploadedFile::getClientOriginalName() already reduces the
        // client-supplied name to its basename; our own defense doesn't rely on
        // that — stored_name/relative_path are always {uuid}.{extension}.
        $this->assertStringNotContainsString('..', $document->original_name);
        $this->assertStringStartsWith('documents/', $document->relative_path);
        $this->assertStringNotContainsString('..', $document->relative_path);
        Storage::disk('local')->assertExists($document->relative_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    private function fixture(string $fixtureName, string $originalName, string $mimeType): UploadedFile
    {
        return new UploadedFile(
            base_path("tests/Fixtures/{$fixtureName}"),
            $originalName,
            $mimeType,
            null,
            true,
        );
    }
}
