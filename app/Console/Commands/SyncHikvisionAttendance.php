<?php

namespace App\Console\Commands;

use App\Services\HikvisionService;
use App\Services\BitrixAttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Services\ServerSyncService;

class SyncHikvisionAttendance extends Command
{
    // The artisan command name used to run this manually from the terminal
    protected $signature = 'attendance:sync';

    protected $description = 'Fetch attendance records from the Hikvision device, store them in the database, and push them to Bitrix24';

    /**
     * Cache key used to remember when the last successful sync completed.
     * Both the morning and evening scheduled runs share the same key so the
     * window is always "from the previous successful run until now".
     */
    protected const LAST_SYNC_CACHE_KEY = 'hikvision_last_sync_at';

    public function handle(  HikvisionService $hikvision,
    BitrixAttendanceService $bitrix,
    ServerSyncService $serverSync): int
    {
        $this->info('Starting sync...');

        $syncLog = \App\Models\SyncLog::create([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            if (! $hikvision->checkDeviceInfo()) {
                $this->error('Could not connect to Hikvision device. Check the IP address or credentials.');
                $syncLog->update([
                    'status' => 'failed',
                    'ended_at' => now(),
                    'error_message' => 'Could not connect to Hikvision device. Check the IP address or credentials.',
                ]);
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
                $syncLog->update([
                    'status' => 'failed',
                    'ended_at' => now(),
                    'error_message' => 'Failed to retrieve attendance records from Hikvision.',
                ]);
                return self::FAILURE;
            }

           if (empty($events)) {
    $this->warn('No new attendance records found in the given time range.');

    // Still try to sync any old events that failed previously.
    $serverResult = $serverSync->syncPendingEvents();

    $this->info(
        "Server sync: {$serverResult['sent']} sent, " .
        "{$serverResult['failed']} failed " .
        "out of {$serverResult['total']} pending events."
    );

    $syncLog->update([
        'status' => $serverResult['failed'] > 0 ? 'failed' : 'success',
        'ended_at' => now(),
        'total_records' => 0,
        'sent_records' => $serverResult['sent'],
        'failed_records' => $serverResult['failed'],
    ]);

    return $serverResult['failed'] > 0
        ? self::FAILURE
        : self::SUCCESS;
}

            $total       = count($events);
            $sentCount   = 0;
            $failedCount = 0;

            $this->info("Fetched {$total} records. Storing to database...");

            // ── Store all events to local database ─────────────────────────────
            $storeResult = $hikvision->storeEvents($events);
            $this->info("✓ Stored {$storeResult['stored']} new events ({$storeResult['skipped']} duplicates skipped).");
            // ───────────────────────────────────────────────────────────────────────

            $this->info("Matching and sending to Bitrix24...");

            foreach ($events as $event) {
                if ($bitrix->sendAttendanceEvent($event)) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            }
            // ── Sync unsent events to Bluehost ──────────────────────────────
$this->info('Sending unsynced events to Bluehost server...');

$serverResult = $serverSync->syncPendingEvents();

$this->info(
    "✓ Server sync: {$serverResult['sent']} sent, " .
    "{$serverResult['failed']} failed " .
    "out of {$serverResult['total']} pending events."
);

if ($serverResult['failed'] > 0) {
    $this->warn(
        "⚠ {$serverResult['failed']} event(s) could not be synced to the server. " .
        "They will be retried on the next sync."
    );
}
// ────────────────────────────────────────────────────────────────
            $this->info("✓ Successfully sent {$sentCount} of {$total} records to Bitrix24.");

            if ($failedCount > 0) {
                $this->warn("⚠ {$failedCount} record(s) were not sent (duplicate or no employee match). Check storage/logs/laravel.log for details.");
            }

            // ── Persist successful sync timestamp ───────────────────────────────
            // Store the end-time of this run so the next scheduled run knows
            // exactly where to pick up from. Keep for 7 days to survive restarts.
            Cache::put(self::LAST_SYNC_CACHE_KEY, $endTime->toIso8601String(), now()->addDays(7));
            $this->info('Last-sync timestamp updated: ' . $endTime->toIso8601String());
            // ───────────────────────────────────────────────────────────────────────

            $syncLog->update([
                'status' => 'success',
                'ended_at' => now(),
                'total_records' => $total,
                'sent_records' => $sentCount,
                'failed_records' => $failedCount,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('An error occurred during sync: ' . $e->getMessage());
            $syncLog->update([
                'status' => 'failed',
                'ended_at' => now(),
                'error_message' => $e->getMessage() . "\n" . $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}