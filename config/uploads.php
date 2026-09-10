<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Upload Policy
    |--------------------------------------------------------------------------
    |
    | Admission rules an UploadSession is validated against (TASK-003). The
    | disk name backs StoredObject.diskName.
    |
    */

    'max_size_bytes' => (int) env('UPLOAD_MAX_SIZE_BYTES', 10485760),

    'disk' => env('UPLOAD_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Content Types
    |--------------------------------------------------------------------------
    |
    | PDF/DOCX admission is a product rule, not deployment config — fixed
    | arrays, no env(). Parallel arrays: index i of allowed_mime_types is the
    | canonical extension for index i of allowed_extensions (ADR-011).
    |
    */

    'allowed_mime_types' => [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    'allowed_extensions' => [
        'pdf',
        'docx',
    ],
];
