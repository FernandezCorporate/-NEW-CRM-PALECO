<?php

namespace App\Services\Push;

use App\Models\PushDevice;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sends notifications through the Firebase Cloud Messaging HTTP v1 API.
 */
class FcmClient implements PushGateway
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public const TOKEN_CACHE_KEY = 'push.fcm.access_token';

    // Firebase answers these when a phone uninstalled the app or the token belongs to another project
    private const DEAD_TOKEN_CODES = ['UNREGISTERED', 'SENDER_ID_MISMATCH'];

    public function isConfigured(): bool
    {
        return filled(config('services.fcm.project_id')) && filled(config('services.fcm.credentials'));
    }

    public function send(array $messages): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'removed' => 0];

        if (! $messages) {
            return $result;
        }

        // Local copies without the Firebase key keep working; nothing is sent
        if (! $this->isConfigured()) {
            Log::info('Push notifications skipped: FCM_PROJECT_ID or FCM_CREDENTIALS is not set.');

            return $result;
        }

        $url = sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', config('services.fcm.project_id'));
        $accessToken = $this->accessToken();

        // One request per phone (the v1 API has no multicast), sent side by side
        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (PushMessage $message) => $pool->withToken($accessToken)->timeout(10)->post($url, ['message' => $message->toFcm()]),
            $messages
        ));

        $deadTokens = [];

        foreach ($messages as $index => $message) {
            $response = $responses[$index] ?? null;

            if ($response instanceof Response && $response->successful()) {
                $result['sent']++;

                continue;
            }

            $result['failed']++;

            if ($response instanceof Response) {
                if ($response->status() === 401) {
                    // The access token expired early; the next send fetches a new one
                    Cache::forget(self::TOKEN_CACHE_KEY);
                }

                if (in_array($this->errorCode($response), self::DEAD_TOKEN_CODES, true)) {
                    $deadTokens[] = $message->token;
                }

                Log::warning('Push notification was not delivered.', [
                    'status' => $response->status(),
                    'error' => $this->errorCode($response) ?? $response->json('error.status'),
                    'type' => $message->type,
                    'user_id' => $message->userId,
                ]);
            } else {
                Log::warning('Push notification could not reach Firebase.', [
                    'error' => $response instanceof Throwable ? $response->getMessage() : 'no response',
                    'type' => $message->type,
                ]);
            }
        }

        if ($deadTokens) {
            $result['removed'] = PushDevice::whereIn('fcm_token', array_unique($deadTokens))->delete();
        }

        return $result;
    }

    /**
     * Short-lived OAuth access token for the service account, cached until a few minutes before it expires.
     */
    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        $credentials = new ServiceAccountCredentials(self::SCOPE, $this->serviceAccountKey());
        $token = $credentials->fetchAuthToken();

        if (empty($token['access_token'])) {
            throw new RuntimeException('Firebase did not return an access token. Check FCM_CREDENTIALS.');
        }

        $lifetime = max(60, (int) ($token['expires_in'] ?? 3600) - 300);
        Cache::put(self::TOKEN_CACHE_KEY, $token['access_token'], now()->addSeconds($lifetime));

        return $token['access_token'];
    }

    /**
     * The service-account key, decoded in memory from the FCM_CREDENTIALS environment variable.
     */
    private function serviceAccountKey(): array
    {
        $key = json_decode((string) base64_decode((string) config('services.fcm.credentials'), true), true);

        if (! is_array($key) || ($key['type'] ?? null) !== 'service_account') {
            throw new RuntimeException('FCM_CREDENTIALS must be the base64-encoded service-account key file.');
        }

        return $key;
    }

    /**
     * The FCM-specific error code (e.g. UNREGISTERED) from an error response.
     */
    private function errorCode(Response $response): ?string
    {
        foreach ((array) $response->json('error.details', []) as $detail) {
            if (isset($detail['errorCode'])) {
                return $detail['errorCode'];
            }
        }

        return null;
    }
}
