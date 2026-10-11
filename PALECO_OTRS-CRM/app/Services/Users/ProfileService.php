<?php

namespace App\Services\Users;

use App\Models\User;

/**
 * Manages user profile data retrieval and relationship loading for the mobile API.
 */
class ProfileService
{
    // --- QUERY METHODS ---

    /**
     * Retrieves the authenticated user and eagerly loads required relationships for resource formatting.
     * Eagerly loads assigned operational teams when the authenticated user is a field personnel.
     */
    public function getProfileData(User $user): User
    {
        $user->load(['role', 'department']);

        if ($user->role?->slug_identifier === 'field_personnel') {
            $user->load('teams');
        }

        return $user;
    }
}
