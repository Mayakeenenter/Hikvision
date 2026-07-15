<?php

namespace App\Console\Commands;

use App\Services\HikvisionService;
use App\Services\BitrixAttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncHikvisionAttendance extends Command
{
    // The artisan command name used to run this manually from the terminal
    protected $signature = 'attendance:sync';

    protected $description = 'Fetch attendance records from the Hikvision device and push them to Bitrix24';

    /**
     * Cache key used to remember when the last successful sync completed.
     * Both the morning and evening scheduled runs share the same key so the
     * window is always "from the previous successful run until now".
     */
    protected const LAST_SYNC_CACHE_KEY = 'hikvision_last_sync_at';

    public function handle(HikvisionService $hikvision, BitrixAttendanceService $bitrix): int
    {
        $this->info('Starting sync...');

        if (! $hikvision->checkDeviceInfo()) {
            $this->error('Could not connect to Hikvision device. Check the IP address or credentials.');
            return self::FAILURE;
        }

        // ── Dynamic time window ─────────────────────────────────────────────
        // Read the timestamp of the last successful sync from cache.
        // If no previous run is recorded (e.g. first run, or cache was cleared)
        // fall back to 24 hours ago so we don't miss any records.
        $lastSyncAt = Cache::get(self::LAST_SYNC_CACHE_KEY);

        $endTime   = now('Asia/Dubai');
        $startTime = $lastSyncAt
            ? \Carbon\Carbon::parse($lastSyncAt, 'Asia/Dubai')
            : $endTime->copy()->subDay();

        $this->info('Sync window: ' . $startTime->toIso8601String() . ' → ' . $endTime->toIso8601String());

        $events = $hikvision->fetchAttendanceEvents(
            $startTime->format('Y-m-d\TH:i:sP'),
            $endTime->format('Y-m-d\TH:i:sP'),
        );
        // ───────────────────────────────────────────────────────────────────────

        if ($events === null) {
            $this->error('Failed to retrieve attendance records from Hikvision.');
            return self::FAILURE;
        }

        if (empty($events)) {
            $this->warn('No attendance records found in the given time range.');
            return self::SUCCESS;
        }

        $total       = count($events);
        $sentCount   = 0;
        $failedCount = 0;

        $this->info("Fetched {$total} records. Matching and sending to Bitrix24...");

        foreach ($events as $event) {
            if ($bitrix->sendAttendanceEvent($event)) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        $this->info("✓ Successfully sent {$sentCount} of {$total} records.");

        if ($failedCount > 0) {
            $this->warn("⚠ {$failedCount} record(s) were not sent (duplicate or no employee match). Check storage/logs/laravel.log for details.");
        }

        // ── Persist successful sync timestamp ───────────────────────────────
        // Store the end-time of this run so the next scheduled run knows
        // exactly where to pick up from. Keep for 7 days to survive restarts.
        Cache::put(self::LAST_SYNC_CACHE_KEY, $endTime->toIso8601String(), now()->addDays(7));
        $this->info('Last-sync timestamp updated: ' . $endTime->toIso8601String());
        // ───────────────────────────────────────────────────────────────────────

        return self::SUCCESS;
    }
}