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

    public function userForm(User $user):bool
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

    public function deactivateConfirm(User $user, User $targetUser): bool
    {
        return $user->role === UserRoles::ADMIN && $targetUser->role !== UserRoles::ADMIN;
    }

    public function deactivate(User $user, User $targetUser): bool
    {
        return $user->role === UserRoles::ADMIN && $targetUser->role !== UserRoles::ADMIN;
    }

    public function reactivateConfirm(User $user, User $targetUser): bool
    {
        return $user->role === UserRoles::ADMIN && $targetUser->role !== UserRoles::ADMIN;
    }

    public function reactivate(User $user, User $targetUser): bool
    {
        return $user->role === UserRoles::ADMIN && $targetUser->role !== UserRoles::ADMIN;
    }
}
