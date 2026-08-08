<?php

namespace App\Jobs;

use App\Services\BitrixAttendanceService;
use App\Services\HikvisionService;
use App\Services\ServerSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessHikvisionBackfillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public string $startTime;
    public string $endTime;

    /**
     * Create a new job instance.
     *
     * @param string $startTime ISO 8601 string or Y-m-d\TH:i:sP format
     * @param string $endTime ISO 8601 string or Y-m-d\TH:i:sP format
     */
    public function __construct(string $startTime, string $endTime)
    {
        $this->startTime = $startTime;
        $this->endTime   = $endTime;
    }

    /**
     * Execute the job.
     */
    public function handle(
        HikvisionService $hikvision,
        BitrixAttendanceService $bitrix,
        ServerSyncService $serverSync
    ): void {
        Log::info("Starting Hikvision backfill job window: {$this->startTime} -> {$this->endTime}");

        if (! $hikvision->checkDeviceInfo()) {
            Log::error('Hikvision backfill job failed: Cannot connect to device.');
            $this->fail(new \Exception('Could not connect to Hikvision device.'));
            return;
        }

        $events = $hikvision->fetchAttendanceEvents($this->startTime, $this->endTime);

        if ($events === null) {
            Log::error("Hikvision backfill job failed to fetch records for window {$this->startTime} to {$this->endTime}.");
            return;
        }

        if (empty($events)) {
            Log::info("No Hikvision events found for window {$this->startTime} to {$this->endTime}.");
            return;
        }

        $storeResult = $hikvision->storeEvents($events);
        Log::info("Hikvision backfill chunk stored {$storeResult['stored']} new events ({$storeResult['skipped']} duplicates skipped).");

        $sentCount = 0;
        $failedCount = 0;
        foreach ($events as $event) {
            if ($bitrix->sendAttendanceEvent($event)) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        $serverResult = $serverSync->syncPendingEvents();

        Log::info("Hikvision backfill chunk complete for {$this->startTime} to {$this->endTime}. Stored: {$storeResult['stored']}, Bitrix Sent: {$sentCount}, Bluehost Sent: {$serverResult['sent']}.");
    }
}
