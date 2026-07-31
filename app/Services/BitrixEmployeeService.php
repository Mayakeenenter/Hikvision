<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitrixEmployeeService
{
    protected string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.bitrix24.webhook_url');
    }

    /**
     * Build and return a map of [ normalized_full_name => bitrix_user_id ]
     * for all active Bitrix24 users. Cached for 6 hours to avoid
     * repeated API calls on every sync run.
     */
    public function getEmployeeMap(): array
    {
        return Cache::remember('bitrix24_employee_map', now()->addHours(6), function () {
            $map   = [];
            $start = 0;

            do {
                $response = Http::retry(3, 200)->get($this->webhookUrl . 'user.get', [
                    'FILTER' => [
                        'ACTIVE' => true,
                    ],
                    'start' => $start,
                ]);

                if ($response->failed()) {
                    Log::error('Bitrix24: failed to fetch employee list', ['response' => $response->body()]);
                    break;
                }

                $data = $response->json();

                foreach ($data['result'] ?? [] as $user) {
                    $fullName = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
                    if ($fullName === '') {
                        continue;
                    }
                    // Store the normalized name as the key and the numeric user ID as the value
                    $map[$this->normalize($fullName)] = (int) $user['ID'];
                }

                $start = $data['next'] ?? null;
            } while ($start !== null);

            return $map;
        });
    }

    /**
     * Normalize a name for comparison: lowercase, trim, collapse whitespace.
     */
    protected function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\s+/u', ' ', $name);
        return $name;
    }

    /**
     * Find the Bitrix24 user ID that best matches the given Hikvision name.
     *
     * Steps:
     *   1. Exact match (after normalization).
     *   2. Fuzzy match using similar_text(); must reach $minSimilarityPercent.
     *
     * Returns the matched user ID, or null if no confident match is found.
     */
    public function findEmployeeId(string $hikvisionName, int $minSimilarityPercent = 80): ?int
    {
        $map              = $this->getEmployeeMap();
        $normalizedTarget = $this->normalize($hikvisionName);

        // Step 1 — exact match
        if (isset($map[$normalizedTarget])) {
            return $map[$normalizedTarget];
        }

        // Step 2 — Unique sub-word containment match
        $targetWords = array_filter(explode(' ', $normalizedTarget));
        if (!empty($targetWords)) {
            $wordMatches = [];
            foreach ($map as $bitrixName => $id) {
                $bitrixWords = array_filter(explode(' ', $bitrixName));
                $allPresent = true;
                foreach ($targetWords as $tWord) {
                    if (!in_array($tWord, $bitrixWords, true)) {
                        $allPresent = false;
                        break;
                    }
                }
                if ($allPresent) {
                    $wordMatches[$bitrixName] = $id;
                }
            }
            if (count($wordMatches) === 1) {
                return reset($wordMatches);
            }
        }

        // Step 3 — fuzzy match
        $bestMatch   = null;
        $bestPercent = 0.0;

        foreach ($map as $bitrixName => $id) {
            similar_text($normalizedTarget, $bitrixName, $percent);
            if ($percent > $bestPercent) {
                $bestPercent = $percent;
                $bestMatch   = $id;
            }
        }

        if ($bestPercent >= $minSimilarityPercent) {
            return $bestMatch;
        }

        Log::warning('Bitrix24: no confident name match found for Hikvision employee', [
            'hikvision_name' => $hikvisionName,
            'best_percent'   => $bestPercent,
        ]);

        return null;
    }
}