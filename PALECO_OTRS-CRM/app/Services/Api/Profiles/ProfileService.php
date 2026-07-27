<?php

namespace App\Services\Api\Profiles;

use App\Models\User;

/*
 * Manages profile data retrieval for the mobile API.
 */
class ProfileService
{
    /*
     * Retrieves the authenticated user and loads necessary relationships for formatting.
     */
    public function getProfileData(User $user): User
    {
        // 1. Load dependencies required by the UserResource class
        return $user->load(['role', 'department']);
    }
}