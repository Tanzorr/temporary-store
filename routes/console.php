<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RetentionSweep (TASK-008): the scheduler container runs `schedule:work`
// and drives this. withoutOverlapping guards against a slow run overlapping
// the next tick; DeleteDocument's own idempotency (I-6) covers the rest.
Schedule::command('documents:sweep-expired')
    ->everyFiveMinutes()
    ->withoutOverlapping();
