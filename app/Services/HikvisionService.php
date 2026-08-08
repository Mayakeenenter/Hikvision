<?php

namespace App\Services;

use App\Models\HikvisionEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class HikvisionService
{
    protected string $deviceIP;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->deviceIP = config('services.hikvision.ip', '') ?? '';
        $this->username = config('services.hikvision.username', '') ?? '';
        $this->password = config('services.hikvision.password', '') ?? '';
    }

    /**
     * Send an HTTP request to the Hikvision device using Digest authentication with retry logic.
     */
    protected function callDevice(string $url, string $method = 'GET', ?array $body = null, int $retries = 3, int $delaySeconds = 1): array
    {
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $ch = \curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }

            $response = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error     = curl_error($ch);
            curl_close($ch);

            $result = [
                'httpCode' => $httpCode,
                'error'    => $error,
                'response' => $response,
            ];

            if (!$error && $httpCode === 200) {
                return $result;
            }

            if ($attempt < $retries) {
                Log::warning("Hikvision connection failed (attempt {$attempt} of {$retries}). Retrying in {$delaySeconds}s...", [
                    'url'      => $url,
                    'httpCode' => $httpCode,
                    'error'    => $error,
                ]);
                sleep($delaySeconds);
            } else {
                return $result;
            }
        }

        return [
            'httpCode' => 0,
            'error'    => 'Unknown failure after retries',
            'response' => null,
        ];
    }

    /**
     * Verify connectivity to the device before fetching data (optional pre-check).
     */
    public function checkDeviceInfo(): bool
    {
        $url    = "http://{$this->deviceIP}/ISAPI/System/deviceInfo";
        $result = $this->callDevice($url);

        if ($result['error'] || $result['httpCode'] !== 200) {
            Log::error('Hikvision: failed to connect to device', $result);
            return false;
        }

        return true;
    }

    /**
     * Fetch all attendance events between two timestamps.
     * Uses pagination to retrieve every record beyond the page size limit.
     */
    public function fetchAttendanceEvents(string $startTime, string $endTime): ?array
    {
        $url       = "http://{$this->deviceIP}/ISAPI/AccessControl/AcsEvent?format=json";
        $allEvents = [];
        $position  = 0;
        $pageSize  = 50;

        do {
            $searchBody = [
                'AcsEventCond' => [
                    'searchID'             => uniqid(),
                    'searchResultPosition' => $position,
                    'maxResults'           => $pageSize,
                    'major'                => 0,
                    'minor'                => 0,
                    'startTime'            => $startTime,
                    'endTime'              => $endTime,
                ],
            ];

            $result = $this->callDevice($url, 'POST', $searchBody);

            if ($result['error'] || $result['httpCode'] !== 200) {
                Log::error('Hikvision: failed to fetch attendance records', $result);
             return null;
            }

            $data      = json_decode($result['response'], true);
            $page      = $data['AcsEvent']['InfoList'] ?? [];
            $total     = (int) ($data['AcsEvent']['totalMatches'] ?? 0);
            $allEvents = array_merge($allEvents, $page);
            $position += count($page);

        } while ($position < $total && count($page) > 0);

        return $allEvents;
    }

    /**
     * Normalize a raw Hikvision event array into a structured record ready for DB storage.
     */
    public function normalizeEvent(array $rawEvent): array
    {
        // Parse time — Hikvision sends ISO 8601 like "2026-07-31T15:51:05+03:00"
        $recordedAt = null;
        $eventDate  = null;
        $eventTime  = null;

        if (!empty($rawEvent['time'])) {
            try {
                $dt = Carbon::parse($rawEvent['time']);
                $recordedAt = $dt;
                $eventDate  = $dt->toDateString();
                $eventTime  = $dt->format('H:i:s');
            } catch (\Exception $e) {
                Log::warning('Hikvision: could not parse event time', ['time' => $rawEvent['time']]);
            }
        }

        // Map event major/minor codes to human-readable type strings
        $eventType  = $this->resolveEventType($rawEvent);
        $statusBadge = $this->resolveStatusBadge($rawEvent);

        return [
            'event_id'       => $rawEvent['eventId'] ?? $rawEvent['serNo'] ?? null,
            'employee_id'    => $rawEvent['employeeNoString'] ?? $rawEvent['employeeNo'] ?? null,
            'employee_name'  => $rawEvent['name'] ?? null,
            'card_number'    => $rawEvent['cardNo'] ?? null,
            'card_reader_id' => $rawEvent['cardReaderNo'] ?? $rawEvent['devIndex'] ?? null,
            'event_type'     => $eventType,
            'sub_type'       => $rawEvent['subType'] ?? $rawEvent['minor'] ?? null,
            'major_type'     => $rawEvent['majorType'] ?? $rawEvent['major'] ?? null,
            'status_badge'   => $statusBadge,
            'recorded_at'    => $recordedAt,
            'event_date'     => $eventDate,
            'event_time'     => $eventTime,
            'remote_host'    => $rawEvent['remoteHostAddr'] ?? null,
            'raw_payload'    => $rawEvent,
        ];
    }

    /**
     * Resolve a human-readable event type string from the raw event payload.
     */
    protected function resolveEventType(array $event): string
    {
        $major = (int) ($event['major'] ?? $event['majorType'] ?? -1);
        $minor = (int) ($event['minor'] ?? $event['subType'] ?? -1);

        // Access Control major = 5
        if ($major === 5) {
            return match ($minor) {
                75  => 'Authenticated',
                76  => 'Authentication Failed',
                82  => 'Door Open',
                83  => 'Door Closed',
                84  => 'Exit Button Released',
                85  => 'Exit Button Pressed',
                default => 'Access Control Event',
            };
        }

        // Fallback: use raw subType string if available
        if (!empty($event['subType']) && is_string($event['subType'])) {
            return ucwords(str_replace(['_', '-'], ' ', $event['subType']));
        }

        return 'Access Control Event';
    }

    /**
     * Resolve a short badge status from the event for visual classification.
     */
    protected function resolveStatusBadge(array $event): string
    {
        $minor = (int) ($event['minor'] ?? $event['subType'] ?? -1);
        $attendanceStatus = $event['attendanceStatus'] ?? null;

        if ($attendanceStatus !== null) {
            return match ((int) $attendanceStatus) {
                0       => 'checkIn',
                1       => 'checkOut',
                default => 'access',
            };
        }

        return match ($minor) {
            75  => 'authenticated',
            76  => 'failed',
            82  => 'doorOpen',
            83  => 'doorClosed',
            84, 85 => 'exitButton',
            default => 'access',
        };
    }

    /**
     * Upsert a batch of normalized events into the database.
     * Uses event_id as the unique key to avoid duplicates.
     */
    public function storeEvents(array $rawEvents): array
    {
        $stored  = 0;
        $skipped = 0;

        foreach ($rawEvents as $raw) {
            $normalized = $this->normalizeEvent($raw);

            // If no event_id, use a hash of the raw payload as unique key
            $eventId = $normalized['event_id'] ?? md5(json_encode($raw));
            $normalized['event_id'] = $eventId;

            // Upsert: update if exists, insert if new
            $exists = HikvisionEvent::where('event_id', $eventId)->exists();

            if ($exists) {
                $skipped++;
            } else {
                HikvisionEvent::create($normalized);
                $stored++;
            }
        }

        Log::info("Hikvision: stored {$stored} new events, skipped {$skipped} duplicates.");

        return ['stored' => $stored, 'skipped' => $skipped];
    }
}