<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRoles;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    } 
}
