<?php

declare(strict_types=1);

namespace App\Domain\Storage;

final class StoredObject
{
    public function __construct(
        public readonly string $disk,
        public readonly string $relativePath,
    ) {}
}
