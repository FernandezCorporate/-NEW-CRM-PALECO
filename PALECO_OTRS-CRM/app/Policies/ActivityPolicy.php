<?php

namespace App\Policies;

use App\Models\User;

class ActivityPolicy
{
    /**
     * Determine whether the user can view the system monitoring logs.
     * Restricts access entirely to the Admin role.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }
}