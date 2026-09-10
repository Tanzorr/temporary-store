<?php

declare(strict_types=1);

namespace App\Domain\Upload;

/**
 * Admission rules an UploadSession is validated against (I-1). Built from
 * config/uploads.php — the only place this whitelist may be edited.
 */
final class UploadPolicy
{
    /**
     * @param  list<string>  $allowedMimeTypes
     * @param  list<string>  $allowedExtensions  Parallel to $allowedMimeTypes: index i is the
     *                                           canonical extension for the MIME type at index i.
     */
    public function __construct(
        public readonly int $maxSizeBytes,
        public readonly array $allowedMimeTypes,
        public readonly array $allowedExtensions,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            maxSizeBytes: (int) config('uploads.max_size_bytes'),
            allowedMimeTypes: config('uploads.allowed_mime_types'),
            allowedExtensions: config('uploads.allowed_extensions'),
        );
    }

    public function allowsSize(int $sizeBytes): bool
    {
        return $sizeBytes <= $this->maxSizeBytes;
    }

    /**
     * The canonical extension for an allowed MIME type, or null if the MIME
     * type is not whitelisted. Never derived from client-supplied input (I-8).
     */
    public function extensionForMimeType(string $mimeType): ?string
    {
        $index = array_search($mimeType, $this->allowedMimeTypes, true);

        return $index === false ? null : $this->allowedExtensions[$index];
    }
}
