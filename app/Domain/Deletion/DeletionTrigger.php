<?php

declare(strict_types=1);

namespace App\Domain\Deletion;

enum DeletionTrigger: string
{
    case MANUAL_DELETION = 'manual_deletion';
    case RETENTION_EXPIRY = 'retention_expiry';
}
