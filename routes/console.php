<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use App\Console\Commands\SyncHikvisionAttendance;
use App\Jobs\SyncBitrixAttendanceJob;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance:sync')
    ->dailyAt('06:00')
    ->timezone('Asia/Dubai')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Scheduled attendance sync failed.');
    });

// ── Bitrix24 batch sync: runs every 5 minutes as a kickstarter. ───────────────
// The job is self-chaining, so a single dispatch processes all pending records.
// withoutOverlapping() ensures only one job-chain is in flight at a time.
Schedule::job(new SyncBitrixAttendanceJob())
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Scheduled Bitrix24 batch sync failed to dispatch.');
    });