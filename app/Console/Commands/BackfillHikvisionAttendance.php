<?php

namespace App\Console\Commands;

use App\Jobs\ProcessHikvisionBackfillJob;
use App\Models\HikvisionEvent;
use App\Services\BitrixAttendanceService;
use App\Services\HikvisionService;
use App\Services\ServerSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillHikvisionAttendance extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:backfill 
                            {--from= : Start date in YYYY-MM-DD format (defaults to 1 year ago)} 
                            {--to= : End date in YYYY-MM-DD format (defaults to today)} 
                            {--chunk-days=7 : Number of days to process per batch chunk} 
                            {--queue : Dispatch batch chunks to Laravel Queue instead of running inline}';

    /**
     * The console command description.
     */
    protected $description = 'Retrieve historical attendance data from the Hikvision device in batch chunks.';

    public function handle(
        HikvisionService $hikvision,
        BitrixAttendanceService $bitrix,
        ServerSyncService $serverSync
    ): int {
        $this->info('Initializing Hikvision historical backfill...');

        if (! $hikvision->checkDeviceInfo()) {
            $this->error('Could not connect to Hikvision device. Please check device IP and credentials.');
            return self::FAILURE;
        }

        $timezone = 'Asia/Dubai';
        $toInput  = $this->option('to');
        $fromInput = $this->option('from');

        $endTime = $toInput 
            ? Carbon::parse($toInput, $timezone)->endOfDay() 
            : now($timezone);

        if ($fromInput) {
            $startTime = Carbon::parse($fromInput, $timezone)->startOfDay();
        } else {
            // Default: check if any event exists, otherwise 1 year ago
            $earliestDb = HikvisionEvent::min('recorded_at');
            $startTime  = $earliestDb 
                ? Carbon::parse($earliestDb, $timezone)->subDays(30)->startOfDay() 
                : now($timezone)->subYear()->startOfDay();
        }

        if ($startTime->gte($endTime)) {
            $this->error("Start time ({$startTime->toDateTimeString()}) must be before end time ({$endTime->toDateTimeString()}).");
            return self::FAILURE;
        }

        $chunkDays = max(1, (int) $this->option('chunk-days'));
        $useQueue  = (bool) $this->option('queue');

        $this->info("Backfill range: {$startTime->toIso8601String()} → {$endTime->toIso8601String()} (Chunk size: {$chunkDays} days)");
        if ($useQueue) {
            $this->info("Mode: Queue Job Dispatching");
        } else {
            $this->info("Mode: Inline Execution");
        }

        $currentStart = $startTime->copy();
        $totalChunks  = 0;
        $totalEventsStored = 0;

        while ($currentStart->lt($endTime)) {
            $currentEnd = $currentStart->copy()->addDays($chunkDays);
            if ($currentEnd->gt($endTime)) {
                $currentEnd = $endTime->copy();
            }

            $startStr = $currentStart->format('Y-m-d\TH:i:sP');
            $endStr   = $currentEnd->format('Y-m-d\TH:i:sP');
            $totalChunks++;

            if ($useQueue) {
                ProcessHikvisionBackfillJob::dispatch($startStr, $endStr);
                $this->info("✓ Queued job #{$totalChunks} for window [{$currentStart->toDateString()} to {$currentEnd->toDateString()}]");
            } else {
                $this->info("\n--- Processing chunk #{$totalChunks}: {$currentStart->toDateString()} → {$currentEnd->toDateString()} ---");
                
                $events = $hikvision->fetchAttendanceEvents($startStr, $endStr);
                
                if ($events === null) {
                    $this->error("Failed to fetch events for window [{$startStr} to {$endStr}]. Skipping to next chunk.");
                } else if (empty($events)) {
                    $this->warn("No events found in window [{$currentStart->toDateString()} to {$currentEnd->toDateString()}].");
                } else {
                    $totalFetched = count($events);
                    $storeResult  = $hikvision->storeEvents($events);
                    $totalEventsStored += $storeResult['stored'];
                    $this->info("Fetched {$totalFetched} records → Stored {$storeResult['stored']} new, skipped {$storeResult['skipped']} duplicates.");

                    $sentCount = 0;
                    foreach ($events as $event) {
                        if ($bitrix->sendAttendanceEvent($event)) {
                            $sentCount++;
                        }
                    }
                    $this->info("Synced {$sentCount}/{$totalFetched} records to Bitrix24.");

                    $serverResult = $serverSync->syncPendingEvents();
                    $this->info("Server sync: {$serverResult['sent']} sent to Bluehost server.");
                }
            }

            $currentStart = $currentEnd->copy();
        }

        $this->newLine();
        if ($useQueue) {
            $this->info("✓ Dispatched {$totalChunks} backfill queue jobs successfully!");
            $this->info("Run 'php artisan queue:work' to process the jobs in the background.");
        } else {
            $this->info("✓ Historical backfill complete! Total new events stored: {$totalEventsStored}");
        }

        return self::SUCCESS;
    }
}
