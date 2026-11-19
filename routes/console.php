<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Only runs if the server's crontab actually calls `php artisan schedule:run`
// — see Phase 14 of docs/IMPLEMENTATION_TASKS_ANNOUNCEMENTS_MEDIA.md, which is
// an infra step for the project owner, not something this code task sets up.
Schedule::command('app:cleanup-abandoned-announcement-chunks')->hourly();
