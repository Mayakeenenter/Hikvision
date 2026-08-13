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
            $recordedAt = $event->recorded_at;
            $eventDate  = $recordedAt?->format('Y-m-d') ?? ($event->event_date ? $event->event_date->format('Y-m-d') : '');
            $eventTime  = $recordedAt?->format('H:i:s') ?? ($event->event_time ?? '');

            // Remote server (keenenter.com) strictly requires non-empty event_id, employee_id, and event_time
            if (empty($event->event_id) || empty($event->employee_id) || empty($eventTime)) {
                Log::warning('Hikvision server sync skipped: missing required fields', [
                    'event_id'    => $event->event_id,
                    'employee_id' => $event->employee_id,
                    'event_time'  => $eventTime,
                ]);

                // Mark record as synced to prevent infinite retry loops on invalid historical data
                $event->update([
                    'synced_to_server' => true,
                ]);

                return false;
            }

            $record = [
                'event_id'        => $event->event_id,
                'employee_id'     => $event->employee_id,
                'employee_name'   => $event->employee_name ?? '',
                'card_number'     => $event->card_number ?? '',
                'card_reader_id'  => $event->card_reader_id ?? '',
                'event_type'      => $event->event_type ?? 'Access Control Event',
                'sub_type'        => $event->sub_type ?? '',
                'major_type'      => $event->major_type ?? '',
                'status_badge'    => $event->status_badge ?? '',
                'recorded_at'     => $recordedAt?->format('Y-m-d H:i:s') ?? '',
                'event_date'      => $eventDate,
                'event_time'      => $eventTime,
                'remote_host'     => $event->remote_host ?? '',
                'raw_payload'     => $event->raw_payload ?? [],
            ];

            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders([
                    'X-Api-Key' => $this->apiKey,
                ])
                ->post($this->serverUrl, [
                    'records' => [$record],
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
                'payload'  => $record,
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
     */
    public function syncPendingEvents(int $chunkSize = 100): array
    {
        $query = HikvisionEvent::where('synced_to_server', false);
        $total = (clone $query)->count();

        $sent = 0;
        $failed = 0;

        (clone $query)->orderBy('id')->chunkById($chunkSize, function ($events) use (&$sent, &$failed) {
            foreach ($events as $event) {
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