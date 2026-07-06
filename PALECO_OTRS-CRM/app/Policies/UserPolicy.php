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

    public function userForm(User $user, ?User $targetUser = null): bool
    {
        if ($user->role !== UserRoles::ADMIN) {
            return false;
        }

        // If a target user is passed (Editing), ensure they are not an Admin
        if ($targetUser && $targetUser->exists) {
            return $targetUser->role !== UserRoles::ADMIN;
        }

        // If no target user is passed (Creating), allow access
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRoles::ADMIN;
    }

    public function update(User $user, User $targetUser): bool
    {
        return $user->role === UserRoles::ADMIN && $targetUser->role !== UserRoles::ADMIN;
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