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
];
