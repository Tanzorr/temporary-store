<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Models\Document;
use Carbon\CarbonImmutable;

/**
 * One row's display values for the CRUD list (ADR-013). Not a domain
 * concept — a read-model projection of Document for a single view.
 */
final class DocumentRow
{
    private function __construct(
        public readonly string $uuid,
        public readonly string $originalName,
        public readonly string $sizeLabel,
        public readonly string $typeLabel,
        public readonly string $uploadedAtLabel,
        public readonly string $expiresAtLabel,
        public readonly bool $awaitingSweep,
        public readonly string $timeRemainingLabel,
    ) {}

    public static function from(Document $document, CarbonImmutable $now): self
    {
        $expiresAt = $document->expires_at->toImmutable();
        $awaitingSweep = $expiresAt->lessThanOrEqualTo($now);

        return new self(
            uuid: $document->uuid,
            originalName: $document->original_name,
            sizeLabel: self::sizeLabel($document->size_bytes),
            typeLabel: strtoupper($document->extension),
            uploadedAtLabel: self::timestampLabel($document->uploaded_at->toImmutable()),
            expiresAtLabel: self::timestampLabel($expiresAt),
            awaitingSweep: $awaitingSweep,
            timeRemainingLabel: $awaitingSweep
                ? 'awaiting sweep'
                : self::timeRemainingLabel($expiresAt, $now),
        );
    }

    private static function sizeLabel(int $sizeBytes): string
    {
        if ($sizeBytes < 1024 * 1024) {
            return number_format($sizeBytes / 1024, 1).' KB';
        }

        return number_format($sizeBytes / (1024 * 1024), 1).' MB';
    }

    private static function timestampLabel(CarbonImmutable $timestamp): string
    {
        return $timestamp->format('Y-m-d H:i').' UTC';
    }

    private static function timeRemainingLabel(CarbonImmutable $expiresAt, CarbonImmutable $now): string
    {
        $seconds = (int) $now->diffInSeconds($expiresAt);

        if ($seconds < 60) {
            return '<1m';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return "{$hours}h {$minutes}m";
    }
}
