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

    /**
     * Send a single real-time attendance event (array payload from the webhook listener).
     * Used by the live event pipeline — keeps existing bool return type for BC.
     */
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

        $response = Http::post($this->webhookUrl . 'lists.element.add', [
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

    // =========================================================================
    // Batch sync support — used by SyncBitrixAttendanceJob
    // =========================================================================

    /**
     * Send a single HikvisionEvent model to Bitrix24 and mark synced_to_bitrix on success.
     *
     * Returns a typed result string:
     *   'sent'         – record added successfully
     *   'duplicate'    – already in cache, skipped
     *   'no_employee'  – no Bitrix24 user match found, skipped
     *   'rate_limited' – Bitrix24 returned 429 (caller should pause)
     *   'error'        – any other HTTP/server error
     */
    public function sendAttendanceRecord(\App\Models\HikvisionEvent $event): string
    {
        $employeeName = $event->employee_name ?? 'Unknown';
        $eventTime    = $event->recorded_at?->toIso8601String() ?? now('Asia/Dubai')->toIso8601String();
        $rawStatus    = $event->status_badge ?? 'unknown';
        $status       = $this->statusMap[$rawStatus] ?? (string) $rawStatus;
        $eventId      = $event->event_id;

        // ── Duplicate check ───────────────────────────────────────────────────
        if ($eventId && Cache::has("hikvision_sent_{$eventId}")) {
            Log::info('Bitrix24 batch: skipping already-synced event', ['event_id' => $eventId]);
            // Keep the DB flag consistent with the cache
            if (! $event->synced_to_bitrix) {
                $event->update(['synced_to_bitrix' => true]);
            }
            return 'duplicate';
        }
        // ─────────────────────────────────────────────────────────────────────

        // ── Employee resolution ───────────────────────────────────────────────
        $bitrixUserId = $this->employeeService->findEmployeeId($employeeName);

        if ($bitrixUserId === null) {
            Log::warning('Bitrix24 batch: no matching employee — skipping', [
                'employee_name' => $employeeName,
                'event_id'      => $eventId,
            ]);
            return 'no_employee';
        }
        // ─────────────────────────────────────────────────────────────────────

        $response = Http::timeout(15)->post($this->webhookUrl . 'lists.element.add', [
            'IBLOCK_TYPE_ID' => $this->IBLOCK_TYPE_ID,
            'IBLOCK_ID'      => $this->IBLOCK_ID,
            'ELEMENT_CODE'   => 'event_' . ($eventId ?? uniqid()),
            'FIELDS'         => [
                'NAME'         => $employeeName . ' (' . $rawStatus . ')',
                'PROPERTY_229' => $bitrixUserId, // Employee
                'PROPERTY_231' => 'Hikvision',   // Source
                'PROPERTY_233' => $status,        // Event Type
                'PROPERTY_235' => $eventTime,     // Event Time
            ],
        ]);

        // ── Rate-limit detection ──────────────────────────────────────────────
        if ($response->status() === 429) {
            Log::warning('Bitrix24 batch: rate limited (429)', ['event_id' => $eventId]);
            return 'rate_limited';
        }
        // ─────────────────────────────────────────────────────────────────────

        if ($response->failed() || isset($response->json()['error'])) {
            Log::error('Bitrix24 batch: failed to add attendance record', [
                'employee_name'  => $employeeName,
                'bitrix_user_id' => $bitrixUserId,
                'event_id'       => $eventId,
                'status'         => $response->status(),
                'response'       => $response->body(),
            ]);
            return 'error';
        }

        // ── Success ───────────────────────────────────────────────────────────
        $event->update(['synced_to_bitrix' => true]);

        if ($eventId) {
            Cache::put("hikvision_sent_{$eventId}", true, now()->addHours(48));
        }

        return 'sent';
    }
}