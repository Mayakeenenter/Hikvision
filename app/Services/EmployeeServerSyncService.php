<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmployeeServerSyncService
{
    protected string $serverUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->serverUrl = config('services.employees_sync.url', '') ?? '';
        $this->apiKey = config('services.employees_sync.api_key', '') ?? '';
    }

    public function syncAll(BitrixEmployeeService $bitrixService): array
    {
        $employees = $bitrixService->getEmployeeListForSync();

        if (empty($employees)) {
            return ['success' => false, 'error' => 'No employees fetched from Bitrix24'];
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders(['X-Api-Key' => $this->apiKey])
                ->post($this->serverUrl, ['employees' => $employees]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Employee sync failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'error' => $response->body()];

        } catch (\Throwable $e) {
            Log::error('Employee sync exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}