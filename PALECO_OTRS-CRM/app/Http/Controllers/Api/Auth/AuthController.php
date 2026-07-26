<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Http\Requests\Api\Auth\MobileLoginRequest;

use App\Services\Api\Auth\MobileAuthService;

/*
 * Manages the API-based authentication lifecycle for the mobile application.
 * Handles credential verification, Sanctum token generation, and secure session termination.
 */
class AuthController extends Controller
{
    // --- MUTATING METHODS ---

    /*
     * Processes the incoming mobile login attempt.
     * Validates credentials via the MobileAuthService and issues a secure API token upon success.
     */
    public function login(MobileLoginRequest $request, MobileAuthService $authService): JsonResponse
    {
        $result = $authService->processLogin(
            $request->validated(), 
            $request->ip()
        );

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message']
            ], $result['status']);
        }

        return response()->json([
            'message' => 'Authentication successful.',
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => $result['user']
        ], Response::HTTP_OK);
    }

    // --- DESTRUCTIVE & STATE METHODS ---

    /*
     * Securely terminates the API session by completely revoking the user's current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out.'
        ], Response::HTTP_OK);
    }
}