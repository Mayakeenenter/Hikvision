<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitrixAttendanceService
{
    protected string $webhookUrl;
    protected int $IBLOCK_ID = 41;
    protected string $IBLOCK_TYPE_ID = 'lists';
    
    protected array $statusMap = [
         'checkIn'  => 167,
        'checkOut' => 169,
        'breakOut' => 'Break-Out',
        'breakIn'  => 'Break-In',
         0          => 167,
         1          => 169,
    ];

  
    public function __construct(
        private readonly BitrixEmployeeService $employeeService
    ) {
        $this->webhookUrl = config('services.bitrix24.webhook_url', '') ?? '';
    }

    public function sendAttendanceEvent(array $event): bool
    {
        $employeeName = $event['name'] ?? $event['employeeNoString'] ?? 'Unknown';
        $eventTime    = $event['time'] ?? now('Asia/Dubai')->toIso8601String();
        $rawStatus    = $event['attendanceStatus'] ?? 'unknown';
        $status       = $this->statusMap[$rawStatus] ?? (string) $rawStatus;

        // ── Duplicate check: skip events already sent to Bitrix24 ──────────────
        $eventId = $event['eventId'] ?? null;
        if ($eventId && Cache::has("hikvision_sent_{$eventId}")) {
            Log::info('Bitrix24: skipping already-synced event', ['eventId' => $eventId]);
            return true;
        }
        // ───────────────────────────────────────────────────────────────────────

        // ── Name matching: resolve Hikvision name → Bitrix24 user ID ────────────
        $bitrixUserId = $this->employeeService->findEmployeeId($employeeName);

        if ($bitrixUserId === null) {
            // No confident match found — skip to avoid sending the record to the wrong person.
            Log::warning('Bitrix24: no matching employee found for Hikvision name — record skipped', [
                'hikvision_name' => $employeeName,
                'eventId'        => $eventId,
                'eventTime'      => $eventTime,
            ]);
            return false;
        }
        // ───────────────────────────────────────────────────────────────────────

        $response = Http::retry(3, 200)->post($this->webhookUrl . 'lists.element.add', [
            'IBLOCK_TYPE_ID' => $this->IBLOCK_TYPE_ID,
            'IBLOCK_ID'      => $this->IBLOCK_ID,
            'ELEMENT_CODE'   => 'event_' . ($eventId ?? uniqid()),
            'FIELDS'         => [
                'NAME'         => $employeeName . ' (' . $rawStatus . ')',
                'PROPERTY_229' => $bitrixUserId, // Employee — matched Bitrix24 user ID
                'PROPERTY_231' => 'Hikvision',   // Source
                'PROPERTY_233' => $status,        // Event Type (Check-In / Check-Out)
                'PROPERTY_235' => $eventTime,     // Event Time
            ],
        ]);

        if ($response->failed() || isset($response->json()['error'])) {
            Log::error('Bitrix24: failed to add attendance record', [
                'hikvision_name' => $employeeName,
                'bitrix_user_id' => $bitrixUserId,
                'event'          => $event,
                'response'       => $response->body(),
            ]);
            return false;
        }

        // Mark this event as sent — keep the flag for 48 hours to prevent duplicates
        if ($eventId) {
            Cache::put("hikvision_sent_{$eventId}", true, now()->addHours(48));
        }

        return true;
    }
}