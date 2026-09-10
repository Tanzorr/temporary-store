<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Document\DocumentStatus;
use App\Domain\Storage\StoredObject;
use Carbon\CarbonImmutable;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'original_name',
        'stored_name',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'disk',
        'relative_path',
        'status',
        'uploaded_at',
        'expires_at',
    ];

    public function storedObject(): StoredObject
    {
        return new StoredObject($this->disk, $this->relative_path);
    }

    /**
     * The one place a read expresses `status = available` (conventions.md).
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('status', DocumentStatus::Available);
    }

    /**
     * Available *and* still inside its retention window (ADR-015). Narrower
     * than `available()`: between `expires_at` and the sweep that acts on it,
     * a Document is listed but must no longer be served.
     */
    public function scopeDownloadable(Builder $query): void
    {
        $query->available()->where('expires_at', '>', CarbonImmutable::now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'uploaded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
