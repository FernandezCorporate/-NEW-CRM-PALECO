<?php

namespace App\Services\Api\Profiles;

use App\Models\User;

class ProfileService
{
    /**
     * Retrieves and formats the authenticated user's profile data.
     */
    public function getProfileData(User $user): array
    {
        $user->load(['role', 'department']);
        
        return [
            'id'          => $user->id,
            'username'    => $user->username,
            'first_name'  => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name'   => $user->last_name,
            'name_ext'    => $user->name_ext,
            'email'       => $user->email,
            'contact'     => $user->contact,
            'role'        => $user->role?->role_name, 
            'department'  => $user->department?->dept_name,
        ];
    }
}