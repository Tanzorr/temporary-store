<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Deletion\DeletionTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeletionEvent extends Model
{
    protected $fillable = [
        'uuid',
        'document_id',
        'trigger',
        'initiator',
        'occurred_at',
        'sweep_id',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => DeletionTrigger::class,
            'occurred_at' => 'datetime',
        ];
    }
}
