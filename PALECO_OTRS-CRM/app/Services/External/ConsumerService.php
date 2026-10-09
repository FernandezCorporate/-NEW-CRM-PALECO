<?php

namespace App\Services\External;

use App\Models\Consumer;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Service handling consumer account synchronization and verification with the external PALECO billing API.
 */
class ConsumerService
{
    // --- QUERY METHODS ---

    /**
     * Frontend live lookup (returns cached consumer or fetches directly from external API without persisting).
     */
    public function verifyAccount(string $accountCode): array
    {
        $consumer = Consumer::where('acct_code', $accountCode)->first();

        if ($consumer) {
            return $consumer->toArray();
        }

        return $this->fetchFromApi($accountCode);
    }

    // --- MUTATING METHODS ---

    /**
     * Resolve consumer ID for ticket submission pipeline.
     * Refreshes consumer details if stale (> 7 days) and falls back to local record if external API is unreachable.
     */
    public function resolveConsumerId(string $accountCode): string
    {
        $consumer = Consumer::where('acct_code', $accountCode)->first();

        if ($consumer && $consumer->updated_at > now()->subDays(7)) {
            return $consumer->id;
        }

        try {
            $data = $this->fetchFromApi($accountCode);

            $syncedConsumer = Consumer::updateOrCreate(
                ['acct_code' => $data['acct_code']],
                [
                    'acct_no' => $data['acct_no'],
                    'name' => $data['name'],
                    'address' => $data['address'],
                    'status' => $data['status'],
                    'meter_serial' => $data['meter_serial'],
                ]
            );

            return $syncedConsumer->id;
        } catch (Exception $e) {
            if ($consumer) {
                return $consumer->id;
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
                throw ValidationException::withMessages([
                    'account_code' => ['The provided account code could not be found in the billing system.'],
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
