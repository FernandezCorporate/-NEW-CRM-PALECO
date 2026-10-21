<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyRecord;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces client-driven idempotency based on the IETF specification.
 * Intercepts requests bearing an X-Idempotency-Key header to prevent duplicate
 * state transitions in intermittent or offline mobile synchronization scenarios.
 */
class HandleIdempotency
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->header('Idempotency-Key');

        // Backward compatibility: If no idempotency key is supplied, proceed normally
        if (! $idempotencyKey) {
            return $next($request);
        }

        // Validate key format and bounds
        if (strlen($idempotencyKey) < 16 || strlen($idempotencyKey) > 64) {
            return response()->json([
                'success' => false,
                'message' => 'The X-Idempotency-Key header is invalid. It must be a UUID or string between 16 and 64 characters.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $requestHash = sha1(
            $request->method().'|'.$request->path().'|'.json_encode($request->except(['photos', 'signature', '_token']))
        );

        // Check for an existing unexpired idempotency record
        $record = IdempotencyRecord::where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->where('expires_at', '>', now())
            ->first();

        if ($record) {
            // Fingerprint integrity guard: Prevent key reuse with a different payload or endpoint
            if ($record->request_hash && $record->request_hash !== $requestHash) {
                return response()->json([
                    'success' => false,
                    'message' => 'Idempotency key has already been used with a different request payload.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($record->status === 'completed') {
                return response()->json($record->response_body, $record->response_code)
                    ->header('X-Cache', 'HIT')
                    ->header('X-Idempotent-Replayed', 'true');
            }

            if ($record->status === 'in_progress') {
                // If created within the last 2 minutes, reject concurrent duplicate execution
                if ($record->created_at && $record->created_at->diffInSeconds(now()) < 120) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Request currently processing, please retry shortly.',
                    ], Response::HTTP_CONFLICT);
                }
            }
        }

        if (! $record) {
            try {
                $record = IdempotencyRecord::create([
                    'user_id' => $user->id,
                    'idempotency_key' => $idempotencyKey,
                    'endpoint_path' => $request->path(),
                    'request_hash' => $requestHash,
                    'status' => 'in_progress',
                    'expires_at' => now()->addDays(7),
                ]);
            } catch (QueryException) {
                // Parallel race condition caught by MySQL unique constraint
                $record = IdempotencyRecord::where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($record && $record->request_hash && $record->request_hash !== $requestHash) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Idempotency key has already been used with a different request payload.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                if ($record && $record->status === 'completed') {
                    return response()->json($record->response_body, $record->response_code)
                        ->header('X-Cache', 'HIT')
                        ->header('X-Idempotent-Replayed', 'true');
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Request currently processing, please retry shortly.',
                ], Response::HTTP_CONFLICT);
            }
        } else {
            $record->update(['status' => 'in_progress']);
        }

        /** @var Response $response */
        $response = $next($request);

        // On success (2xx), cache response body and code for subsequent replays
        if ($response->isSuccessful()) {
            $content = $response->getContent();
            $decoded = json_decode($content, true);

            $record->update([
                'status' => 'completed',
                'response_code' => $response->getStatusCode(),
                'response_body' => $decoded,
            ]);

            return $response
                ->header('X-Cache', 'MISS')
                ->header('X-Idempotent-Key', $idempotencyKey);
        }

        // On failure (4xx or 5xx), remove in-progress record to allow future corrected retry
        $record->delete();

        return $response;
    }
}
