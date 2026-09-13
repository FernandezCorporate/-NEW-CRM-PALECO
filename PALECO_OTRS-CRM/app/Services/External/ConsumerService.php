<?php

namespace App\Services\External;

use App\Models\Consumer;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Exception;

class ConsumerService
{
    /*
     * Resolves an account code to a local Consumer ID.
     * Checks the local database first, then falls back to the external API if missing.
     */
    public function resolveConsumerId(string $accountCode): string
    {
        // 1. Local Cache Lookup
        $consumer = Consumer::where('acct_code', $accountCode)->first();
        
        if ($consumer) {
            return $consumer->id;
        }

        try {
            // 2. External API Fetch
            // - withoutVerifying() bypasses local SSL certificate issues (cURL error 60).
            // - timeout(15) prevents the page from hanging indefinitely if the external server is down.
            $response = Http::withoutVerifying()
                ->timeout(15)
                ->withHeaders([
                    'x-api-key' => config('services.paleco.key'),
                    'Accept'    => '*/*' // Matches Postman's default Accept header
                ])
                ->get("http://api.paleco.net/api/v1/account/{$accountCode}");

            // 3. Handle Logical API Failure (e.g., 404 Not Found)
            if ($response->failed() || ! $response->json('success')) {
                throw ValidationException::withMessages([
                    'account_code' => ['The provided account code could not be found in the billing system.']
                ]);
            }

            // 4. Hydrate Local Database & Return New ID
            $data = $response->json('data');
            
            $newConsumer = Consumer::create([
                'acct_no'      => $data['acct_no'],
                'acct_code'    => $data['acct_code'],
                'name'         => $data['name'],
                'address'      => $data['address'],
                'status'       => $data['status'],
                'meter_serial' => $data['meter_serial'],
            ]);

            return $newConsumer->id;

        } catch (ValidationException $e) {
            // Re-throw the logical validation error so it reaches the frontend
            throw $e;
        } catch (Exception $e) {
            // 5. Handle Network/cURL Failures
            // Intercepts fatal connection errors (SSL failures, Timeouts, DNS issues)
            // Bounces the user back to the form with a clean validation message instead of a 500 crash page.
            throw ValidationException::withMessages([
                'account_code' => ['Unable to connect to the external billing database. Please check the network connection or try again later.']
            ]);
        }
    }
}