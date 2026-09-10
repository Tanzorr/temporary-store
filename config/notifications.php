<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Recipient
    |--------------------------------------------------------------------------
    |
    | The operator mailbox told about deletions (NotificationRecipient).
    | Address comes from the environment, never from a Document (I-9).
    |
    */

    'recipient_email' => env('DELETION_NOTIFY_EMAIL'),
];
