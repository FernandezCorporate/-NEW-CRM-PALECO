<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\MobileLoginRequest;
use App\Services\Api\Auth\MobileAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Handle the incoming mobile login request.
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

    /**
     * Securely terminate the API session by revoking the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out and token revoked.'
        ], Response::HTTP_OK);
    }
}