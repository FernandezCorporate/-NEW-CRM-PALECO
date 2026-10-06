<?php

namespace App\Services\Api\Profiles;

use App\Models\User;

/*
 * Manages user profile data retrieval and relationship loading for the mobile API.
 */
class ProfileService
{
    // --- QUERY METHODS ---

    /*
     * Retrieves the authenticated user and eagerly loads required relationships for resource formatting.
     */
    public function getProfileData(User $user): User
    {
        return $user->load(['role', 'department']);
    }
}
