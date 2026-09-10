<?php

declare(strict_types=1);

namespace App\Domain\Document;

enum DocumentStatus: string
{
    case Available = 'available';
    case Deleted = 'deleted';
}
