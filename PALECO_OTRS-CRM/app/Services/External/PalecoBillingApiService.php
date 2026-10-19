<?php

namespace App\Services\External;

use App\Models\Consumer;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Service handling consumer account synchronization, multi-criteria searching,
 * and verification with the external PALECO billing API.
 */
class PalecoBillingApiService
{
    // --- QUERY METHODS ---

    /**
     * Frontend live lookup (returns cached consumer or fetches directly from external API without persisting).
     */
    public function verifyAccount(string $accountCode): array
    {
        $consumer = Consumer::where('acct_code', $accountCode)->first();

        if ($consumer) {
            $data = $consumer->toArray();
            $data['status'] = $this->normalizeStatus($data['status'] ?? null);

            return $data;
        }

        $data = $this->fetchFromApi($accountCode);
        $data['status'] = $this->normalizeStatus($data['status'] ?? null);

        return $data;
    }

    /**
     * Search consumers against external PALECO billing API within Puerto Princesa scope (database=paleco_db).
     * Falls back to local database search if external service is unreachable.
     *
     * @param  string|null  $lastName Last name or Lastname,Firstname search query
     * @param  string|null  $meterSerial Optional meter serial query
     * @param  int  $limit Maximum results to return (default 30)
     * @return array{data: array, has_more: bool, source: string}
     */
    public function searchAccounts(?string $lastName = null, ?string $meterSerial = null, int $limit = 30): array
    {
        $query = [
            'database' => 'paleco_db',
            'limit' => $limit,
        ];

        if (! empty($lastName)) {
            $query['lastname'] = trim($lastName);
        }

        if (! empty($meterSerial)) {
            $query['meter_serial'] = trim($meterSerial);
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(15)
                ->withHeaders([
                    'x-api-key' => config('services.paleco.key'),
                    'Accept' => '*/*',
                ])
                ->get('https://api.paleco.net/api/v1/accounts/search', $query);

            if ($response->successful() && $response->json('success')) {
                $rawAccounts = $response->json('data') ?? [];
                $hasMore = (bool) ($response->json('has_more') ?? false);

                $normalized = array_map(function ($account) {
                    $account['status'] = $this->normalizeStatus($account['status'] ?? null);

                    return $account;
                }, $rawAccounts);

                return [
                    'data' => $normalized,
                    'has_more' => $hasMore,
                    'source' => 'api',
                ];
            }

            if ($response->status() === 404) {
                return [
                    'data' => [],
                    'has_more' => false,
                    'source' => 'api',
                ];
            }

            throw new Exception($response->json('message') ?? 'Search request failed.');

        } catch (Exception $e) {
            return $this->searchLocalConsumers($lastName, $meterSerial, $limit);
        }
    }

    /**
     * Local database fallback for consumer search.
     *
     * @return array{data: array, has_more: bool, source: string}
     */
    public function searchLocalConsumers(?string $lastName = null, ?string $meterSerial = null, int $limit = 30): array
    {
        $localResults = Consumer::query()
            ->searchByNameAndMeter($lastName, $meterSerial)
            ->limit($limit)
            ->get()
            ->map(function ($c) {
                $arr = $c->toArray();
                $arr['status'] = $this->normalizeStatus($arr['status'] ?? null);

                return $arr;
            })
            ->values()
            ->toArray();

        return [
            'data' => $localResults,
            'has_more' => false,
            'source' => 'local',
        ];
    }

    /**
     * Normalizes single-letter or raw status codes into human-readable text.
     */
    public function normalizeStatus(?string $rawStatus): string
    {
        return match (strtoupper(trim($rawStatus ?? ''))) {
            'A', 'ACTIVE' => 'Active',
            'D', 'DEACTIVATED' => 'Deactivated',
            'I', 'INACTIVE' => 'Inactive',
            default => 'Unknown',
        };
    }

    // --- MUTATING METHODS ---

    /**
     * Synchronize and persist consumer record with normalized status.
     */
    public function syncConsumer(array $data): Consumer
    {
        return Consumer::updateOrCreate(
            ['acct_code' => $data['acct_code']],
            [
                'acct_no' => $data['acct_no'],
                'name' => $data['name'],
                'address' => $data['address'],
                'status' => $this->normalizeStatus($data['status'] ?? null),
                'meter_serial' => $data['meter_serial'] ?? null,
            ]
        );
    }

    /**
     * Resolve consumer ID for ticket submission pipeline.
     * Refreshes consumer details if stale (> 7 days) and falls back to local record or verified search payload
     * if external API is unreachable.
     */
    public function resolveConsumerId(string $accountCode, array $fallbackData = []): string
    {
        $consumer = Consumer::where('acct_code', $accountCode)->first();

        if ($consumer && $consumer->updated_at > now()->subDays(7)) {
            return $consumer->id;
        }

        try {
            $data = $this->fetchFromApi($accountCode);

            $syncedConsumer = $this->syncConsumer($data);

            return $syncedConsumer->id;
        } catch (Exception $e) {
            $existingConsumer = Consumer::where('acct_code', $accountCode)->first();
            if ($existingConsumer) {
                return $existingConsumer->id;
            }

            if ($consumer) {
                return $consumer->id;
            }

            if (! empty($fallbackData['name']) && ! empty($fallbackData['acct_code'])) {
                $syncedConsumer = $this->syncConsumer($fallbackData);

                return $syncedConsumer->id;
            }

            throw $e;
        }
    }

    // --- PRIVATE HELPER METHODS ---

    /**
     * Fetch raw consumer account details from the external billing API.
     *
     * @throws ValidationException
     */
    private function fetchFromApi(string $accountCode): array
    {
        try {
            $response = Http::withoutVerifying()
                ->timeout(15)
                ->withHeaders([
                    'x-api-key' => config('services.paleco.key'),
                    'Accept' => '*/*',
                ])
                ->get("https://api.paleco.net/api/v1/account/{$accountCode}");

            if ($response->failed() || ! $response->json('success')) {
                $message = $response->json('message') ?? 'The provided account code could not be found in the billing system.';
                throw ValidationException::withMessages([
                    'account_code' => [$message],
                ]);
            }

            return $response->json('data');

        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'account_code' => ['Unable to connect to the external billing database.'],
            ]);
        }
    }
}

