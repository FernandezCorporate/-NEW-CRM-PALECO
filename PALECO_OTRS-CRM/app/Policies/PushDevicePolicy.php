<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only accounts that use the mobile app can register a phone for push notifications.
 */
class PushDevicePolicy
{
    /**
     * Determine whether the user can register or remove the phone of their current login.
     */
    public function register(User $user): Response
    {
        return $user->hasRole('supervisor', 'field_personnel')
            ? Response::allow()
            : Response::deny('Only mobile app accounts can receive push notifications.');
    }
}
