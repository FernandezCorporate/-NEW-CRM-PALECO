<?php

namespace App\Services\External;

use App\Models\Consumer;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Exception;

class ConsumerService
{
    /**
     * Reusable API Fetcher (Read-Only)
     */
    private function fetchFromApi(string $accountCode): array
    {
        try {
            $response = Http::withoutVerifying()
                ->timeout(15)
                ->withHeaders([
                    'x-api-key' => config('services.paleco.key'),
                    'Accept'    => '*/*'
                ])
                ->get("https://api.paleco.net/api/v1/account/{$accountCode}");

            if ($response->failed() || ! $response->json('success')) {
                throw ValidationException::withMessages([
                    'account_code' => ['The provided account code could not be found in the billing system.']
                ]);
            }

            return $response->json('data');

        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'account_code' => ['Unable to connect to the external billing database.']
            ]);
        }
    }

    /**
     * Frontend Live Lookup (Does NOT insert into database)
     */
    public function verifyAccount(string $accountCode): array
    {
        $consumer = Consumer::where('acct_code', $accountCode)->first();
        
        if ($consumer) {
            return $consumer->toArray();
        }

        // Fetch directly from API and pass to frontend without saving
        return $this->fetchFromApi($accountCode);
    }

    /**
     * Ticket Submission Pipeline (Triggers Database Insertion)
     */
    public function resolveConsumerId(string $accountCode): string
    {
        $consumer = Consumer::where('acct_code', $accountCode)->first();
        
        if ($consumer) {
            return $consumer->id;
        }

        $data = $this->fetchFromApi($accountCode);
        
        $newConsumer = Consumer::updateOrCreate(
            ['acct_code' => $data['acct_code']],
            [
                'acct_no'      => $data['acct_no'],
                'name'         => $data['name'],
                'address'      => $data['address'],
                'status'       => $data['status'],
                'meter_serial' => $data['meter_serial'],
            ]
        );

        return $newConsumer->id;
    }
}