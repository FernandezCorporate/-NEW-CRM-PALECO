<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRoles;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }

    public function departmentForm(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }

    public function update(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }

    public function deleteConfirm(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }

    public function archive(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }

    public function delete(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }

    public function restore(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }

    public function forceDelete(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }   
}
