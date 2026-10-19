<?php

namespace App\Http\Controllers\Api\Profiles;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Services\Users\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/*
 * Manages profile data retrieval for authenticated users within the mobile API.
 */
class ProfileController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Fetches the authenticated user's profile details.
     */
    public function show(Request $request): JsonResponse
    {
        Gate::authorize('viewProfile', $request->user());

        $userModel = $this->userService->getProfileData($request->user());

        return response()->json([
            'success' => true,
            'data' => new UserResource($userModel),
        ], Response::HTTP_OK);
    }
}
