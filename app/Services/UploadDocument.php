<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Document\DocumentStatus;
use App\Domain\Retention\RetentionPolicy;
use App\Domain\Upload\MimeTypeDetector;
use App\Domain\Upload\UploadPolicy;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Admits one already-validated UploadedFile (UploadDocumentRequest has
 * already enforced UploadPolicy) and stores it as a Document. The stored
 * path is always {uuid}.{extension} — originalName never becomes a path
 * segment (I-7), so a traversal-crafted filename has nothing to act on.
 */
final class UploadDocument
{
    public function __construct(
        private readonly RetentionPolicy $retentionPolicy,
    ) {}

    public function handle(UploadedFile $file): Document
    {
        $policy = UploadPolicy::fromConfig();
        $mimeType = MimeTypeDetector::detect($file->getPathname());
        $extension = $policy->extensionForMimeType($mimeType);

        if ($extension === null) {
            throw new RuntimeException("UploadDocument received an unwhitelisted MIME type: {$mimeType}");
        }

        $disk = (string) config('uploads.disk');
        $uuid = (string) Str::uuid();
        $storedName = "{$uuid}.{$extension}";
        $checksum = hash_file('sha256', $file->getPathname());
        $uploadedAt = CarbonImmutable::now();

        $relativePath = Storage::disk($disk)->putFileAs('documents', $file, $storedName);

        if ($relativePath === false) {
            throw new RuntimeException("Failed to store uploaded file on disk [{$disk}].");
        }

        return Document::create([
            'uuid' => $uuid,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size_bytes' => $file->getSize(),
            'checksum_sha256' => $checksum,
            'disk' => $disk,
            'relative_path' => $relativePath,
            'status' => DocumentStatus::Available,
            'uploaded_at' => $uploadedAt,
            'expires_at' => $this->retentionPolicy->deadlineFor($uploadedAt),
        ]);
    }
}
