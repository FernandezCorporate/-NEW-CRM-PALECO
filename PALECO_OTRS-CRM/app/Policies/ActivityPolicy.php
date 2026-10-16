<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

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

    /**
     * Determine whether the user can view a specific activity log entry.
     * Restricts access entirely to the Admin role.
     */
    public function view(User $user, Activity $activity): bool
    {
        return $user->role->slug_identifier === 'admin';
    }
}

