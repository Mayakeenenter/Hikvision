<?php

namespace App\Jobs;

use App\Models\HikvisionEvent;
use App\Services\BitrixAttendanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncBitrixAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * No built-in retry limit — the job manages its own backoff via release().
     * Set a high number so Laravel doesn't expire it before all records are processed.
     */
    public int $tries = 100;

    /**
     * Seconds before the job is considered timed out by the queue worker.
     * 20 records × ~1.5 s each (request + sleep) = ~30 s; 120 s is a generous ceiling.
     */
    public int $timeout = 120;

    /**
     * Number of records to process per job run.
     * 20 records at 2 req/s = ~10 s. Comfortably under the 50-burst cap.
     */
    private int $chunkSize;

    /**
     * Seconds to sleep between each request to honour the 2 req/s limit.
     * 500 000 µs = 0.5 s  →  at most 2 requests per second.
     */
    private const INTER_REQUEST_SLEEP_US = 500_000;

    /**
     * Seconds to wait before re-queuing when rate-limited by Bitrix24.
     */
    private const RATE_LIMIT_BACKOFF_SECONDS = 60;

    public function __construct(int $chunkSize = 20)
    {
        $this->chunkSize = $chunkSize;
    }

    /**
     * Execute the job.
     */
    public function handle(BitrixAttendanceService $bitrix): void
    {
        $events = HikvisionEvent::unsyncedToBitrix()
            ->orderBy('id')
            ->limit($this->chunkSize)
            ->get();

        if ($events->isEmpty()) {
            Log::info('Bitrix24 batch sync: no pending records — job done.');
            return;
        }

        Log::info("Bitrix24 batch sync: processing {$events->count()} records.");

        $counts = ['sent' => 0, 'duplicate' => 0, 'no_employee' => 0, 'error' => 0, 'rate_limited' => 0];

        foreach ($events as $event) {
            $result = $bitrix->sendAttendanceRecord($event);

            $counts[$result] = ($counts[$result] ?? 0) + 1;

            if ($result === 'rate_limited') {
                // ── Bitrix24 is throttling us ─────────────────────────────────
                // Put the job back on the queue after a 60-second cooldown.
                // The current event was NOT marked synced, so it will be retried.
                Log::warning('Bitrix24 batch sync: rate limited — releasing job for 60 s.', [
                    'sent_so_far' => $counts['sent'],
                ]);
                $this->release(self::RATE_LIMIT_BACKOFF_SECONDS);
                return; // exit immediately; the released job will continue from here
            }

            // Throttle: sleep 500 ms between requests to stay at ≤ 2 req/s
            if ($result !== 'no_employee' && $result !== 'duplicate') {
                usleep(self::INTER_REQUEST_SLEEP_US);
            }
        }

        Log::info('Bitrix24 batch sync: chunk complete.', $counts);

        // ── Check if more records remain and self-chain ───────────────────────
        $remaining = HikvisionEvent::unsyncedToBitrix()->count();
        if ($remaining > 0) {
            Log::info("Bitrix24 batch sync: {$remaining} records remain — dispatching next chunk.");
            static::dispatch($this->chunkSize);
        } else {
            Log::info('Bitrix24 batch sync: all records synced.');
        }
    }
}
