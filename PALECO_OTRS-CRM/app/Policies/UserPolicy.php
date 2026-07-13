<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    public function userForm(User $user, ?User $targetUser = null): bool
    {
        if ($user->role->slug_identifier !== 'admin') {
            return false;
        }

        // If a target user is passed (Editing), ensure they are not an Admin
        if ($targetUser && $targetUser->exists) {
            return $targetUser->role->slug_identifier !== 'admin' || $user->is($targetUser);
        }

        return true;
    }

    public function view(User $user): bool{
        return $user->role->slug_identifier === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    public function update(User $user, User $targetUser): bool
    {
        return $user->role->slug_identifier === 'admin' && 
               ($targetUser->role->slug_identifier !== 'admin' || $user->is($targetUser));
    }

    public function deactivateConfirm(User $user, User $targetUser): bool
    {
        return $user->role->slug_identifier === 'admin' && $targetUser->role->slug_identifier !== 'admin';
    }

    public function deactivate(User $user, User $targetUser): bool
    {
        return $user->role->slug_identifier === 'admin' && $targetUser->role->slug_identifier !== 'admin';
    }

    public function reactivateConfirm(User $user, User $targetUser): bool
    {
        return $user->role->slug_identifier === 'admin' && $targetUser->role->slug_identifier !== 'admin';
    }

    public function reactivate(User $user, User $targetUser): bool
    {
        return $user->role->slug_identifier === 'admin' && $targetUser->role->slug_identifier !== 'admin';
    }
}