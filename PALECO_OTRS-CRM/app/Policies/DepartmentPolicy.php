<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Enums\UserRoles;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }

    public function createForm(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }
}
