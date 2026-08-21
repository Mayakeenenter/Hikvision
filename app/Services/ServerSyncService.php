<?php

namespace App\Services;

use App\Models\HikvisionEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ServerSyncService
{
    protected string $serverUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->serverUrl = config('services.hikvision_sync.url', '') ?? '';
        $this->apiKey = config('services.hikvision_sync.api_key', '') ?? '';
    }

    /**
     * Send one local Hikvision event to the Bluehost server.
     */
    public function sendEvent(HikvisionEvent $event): bool
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders([
                    'X-Api-Key' => $this->apiKey,
                ])
                ->post($this->serverUrl, [
                    'records' => [
                        [
                            'event_id'        => $event->event_id,
                            'employee_id'      => $event->employee_id,
                            'employee_name'    => $event->employee_name,
                            'card_number'      => $event->card_number,
                            'card_reader_id'   => $event->card_reader_id,
                            'event_type'       => $event->event_type ?? 'Access Control Event',
                            'sub_type'         => $event->sub_type,
                            'major_type'       => $event->major_type,
                            'status_badge'     => $event->status_badge,
                            'recorded_at'      => $event->recorded_at?->format('Y-m-d H:i:s'),
                            'event_date'       => $event->recorded_at?->format('Y-m-d'),
                            'event_time'       => $event->recorded_at?->format('H:i:s'),
                            'remote_host'      => $event->remote_host,
                            'raw_payload'      => $event->raw_payload,
                        ],
                    ],
                ]);

            if ($response->successful() && $response->json('success')) {
                $event->update([
                    'synced_to_server' => true,
                ]);

                return true;
            }

            Log::error('Hikvision server sync failed', [
                'event_id' => $event->event_id,
                'status'   => $response->status(),
                'response' => $response->body(),
            ]);

            return false;

        } catch (\Throwable $e) {

            Log::error('Hikvision server sync exception', [
                'event_id' => $event->event_id,
                'error'    => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send all local events that have not been synced yet.
     * Uses chunk() to avoid loading all records into memory at once.
     */
    public function syncPendingEvents(): array
    {
        $total  = 0;
        $sent   = 0;
        $failed = 0;

        HikvisionEvent::where('synced_to_server', false)
            ->orderBy('id')
            ->chunk(100, function ($events) use (&$total, &$sent, &$failed) {
                foreach ($events as $event) {
                    $total++;
                    if ($this->sendEvent($event)) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                }
            });

        return [
            'total'  => $total,
            'sent'   => $sent,
            'failed' => $failed,
        ];
    }
}