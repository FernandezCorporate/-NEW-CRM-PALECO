<?php

namespace App\Http\Controllers\Api\Profiles;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Services\Api\Profiles\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /*
     * Inject the ProfileService into the controller.
     */
    public function __construct(protected ProfileService $profileService) {}

    /*
     * Fetch the authenticated user's profile details.
     */
    public function show(Request $request): JsonResponse
    {
        $userModel = $this->profileService->getProfileData($request->user());

        return response()->json([
            'success' => true,
            'status'  => 200,
            'data'    => new UserResource($userModel)
        ]);
    }
}