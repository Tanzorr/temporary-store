<?php

declare(strict_types=1);

namespace App\Domain\Upload;

use finfo;

/**
 * Content-based MIME detection (ADR-006, I-8). Reused by UploadDocumentRequest
 * and UploadDocument so the two halves of UploadSession never disagree on it.
 */
final class MimeTypeDetector
{
    public static function detect(string $path): string
    {
        return (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
    }
}
