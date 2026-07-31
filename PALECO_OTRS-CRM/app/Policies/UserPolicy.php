<?php

namespace App\Policies;

use App\Models\User;

/*
 * Defines RBAC (Role-based access controls) for each available action on a user resource.
 * Implemented as gate checks at the start of every CRUD method on the UserController.
 */
class UserPolicy
{

    // Web-app permissions
    public function viewAny(User $user): bool { return $user->role->slug_identifier === 'admin'; }
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
    public function view(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function create(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function update(User $user, User $targetUser): bool { return $user->role->slug_identifier === 'admin' && ($targetUser->role->slug_identifier !== 'admin' || $user->is($targetUser)); }
    public function deactivateConfirm(User $user, User $targetUser): bool { return $user->role->slug_identifier === 'admin' && $targetUser->role->slug_identifier !== 'admin'; }
    public function deactivate(User $user, User $targetUser): bool { return $user->role->slug_identifier === 'admin' && $targetUser->role->slug_identifier !== 'admin'; }
    public function reactivateConfirm(User $user, User $targetUser): bool { return $user->role->slug_identifier === 'admin' && $targetUser->role->slug_identifier !== 'admin'; }
    public function reactivate(User $user, User $targetUser): bool { return $user->role->slug_identifier === 'admin' && $targetUser->role->slug_identifier !== 'admin'; }

    // Mobile app permissions
    public function viewProfile(User $user, User $targetUser): bool { return $user->is($targetUser) && in_array($user->role->slug_identifier, ['admin', 'cwd_officer', 'foreman', 'field_personnel']); }

    /*
     * Web-app
     * viewAny           => Admin only; view all user accounts.
     * userForm          => Admin only; dynamically applied for a create or update form. 
     *                   => If used to check edit form permissions, prevents editing other admin account records unless the admin account is the current user.
     * view              => Admin only; view specific account details.
     * create            => Admin only; add a new user account.
     * update            => Admin only; prevents editing other admin account records unless the admin account is the current user.
     * deactivateConfirm => Admin only but not allowed if target user is Admin; access the deactivate confirmation prompt
     * deactivate        => Admin only but not allowed if target user is Admin; perform account deactivation
     * reactivateConfirm => Admin only but not allowed if target user is Admin; acccess the reactivate confirmation prompt
     * reactivate        => Admin only but not allowed if target user is Admin; perform account reactivation
     * 
     * Mobile app
     * viewProfile:      => Allows a user account to retrieve their own account information only.
     *                   => Also checks if the reqeusting user's role is valid.
     */
}