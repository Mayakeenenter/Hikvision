<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class HikvisionService
{
    protected string $deviceIP;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->deviceIP = config('services.hikvision.ip');
        $this->username = config('services.hikvision.username');
        $this->password = config('services.hikvision.password');
    }

    /**
     * Send an HTTP request to the Hikvision device using Digest authentication.
     */
    protected function callDevice(string $url, string $method = 'GET', ?array $body = null): array
    {
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

        return [
            'httpCode' => $httpCode,
            'error'    => $error,
            'response' => $response,
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
}