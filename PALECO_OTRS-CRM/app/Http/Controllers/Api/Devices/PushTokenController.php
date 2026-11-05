<?php

namespace App\Http\Controllers\Api\Devices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Devices\RegisterPushTokenRequest;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/*
 * Registers and removes the phone that receives push notifications for the current mobile login.
 */
class PushTokenController extends Controller
{
    // --- MUTATING METHODS ---

    /*
     * Saves the phone's Firebase token for this login, or refreshes it when Firebase issues a new one.
     */
    public function store(RegisterPushTokenRequest $request): JsonResponse
    {
        Gate::authorize('register', PushDevice::class);

        $accessToken = $request->user()->currentAccessToken();

        // A phone is tied to a stored login token, so a token with no database record can't register one
        if (! $accessToken instanceof PersonalAccessToken || ! $accessToken->getKey()) {
            return response()->json([
                'success' => false,
                'message' => 'A mobile login token is required to register this device.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $fcmToken = $request->validated('fcm_token');

        $device = DB::transaction(function () use ($request, $accessToken, $fcmToken) {
            // The same phone may still be registered under an older login or another account (shared phone)
            PushDevice::where('fcm_token', $fcmToken)
                ->where('personal_access_token_id', '!=', $accessToken->id)
                ->delete();

            return PushDevice::updateOrCreate(
                ['personal_access_token_id' => $accessToken->id],
                [
                    'user_id' => $request->user()->id,
                    'fcm_token' => $fcmToken,
                    'platform' => $request->validated('platform') ?? 'android',
                    'last_seen_at' => now(),
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'This device will now receive notifications.',
            'data' => [
                'id' => $device->id,
                'platform' => $device->platform,
                'registered_at' => $device->created_at,
            ],
        ], $device->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /*
     * Stops push notifications to this phone, e.g. when the user turns notifications off in the app.
     */
    public function destroy(Request $request): JsonResponse
    {
        Gate::authorize('register', PushDevice::class);

        $accessToken = $request->user()->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken && $accessToken->getKey()) {
            PushDevice::where('personal_access_token_id', $accessToken->id)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'This device will no longer receive notifications.',
        ], Response::HTTP_OK);
    }
}
