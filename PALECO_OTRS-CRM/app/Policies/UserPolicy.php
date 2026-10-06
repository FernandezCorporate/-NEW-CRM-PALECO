<?php

namespace App\Policies;

use App\Models\User;

/*
 * Defines role-based access controls for managing user accounts across web and mobile surfaces.
 */
class UserPolicy
{
    // --- WEB ADMINISTRATOR PERMISSIONS ---

    /*
     * Determine whether the user can browse user account listings.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    /*
     * Determine whether the user can access user account creation or modification forms.
     */
    public function userForm(User $user, ?User $targetUser = null): bool
    {
        if ($user->role->slug_identifier !== 'admin') {
            return false;
        }

        if ($targetUser && $targetUser->exists) {
            return $targetUser->role->slug_identifier !== 'admin' || $user->is($targetUser);
        }

        return true;
    }

    /*
     * Determine whether the user can view a specific user account's profile details.
     */
    public function view(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    /*
     * Determine whether the user can create new user accounts.
     */
    public function create(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    /*
     * Determine whether the user can update an existing user account profile.
     */
    public function update(User $user, User $targetUser): bool
    {
        return $user->role->slug_identifier === 'admin'
            && ($targetUser->role->slug_identifier !== 'admin' || $user->is($targetUser));
    }

    /*
     * Determine whether the user can access the deactivation confirmation prompt.
     */
    public function deactivateConfirm(User $user, User $targetUser): bool
    {
        return $user->role->slug_identifier === 'admin'
            && $targetUser->role->slug_identifier !== 'admin';
    }

    /*
     * Determine whether the user can deactivate a user account.
     */
    public function deactivate(User $user, User $targetUser): bool
    {
        return $user->role->slug_identifier === 'admin'
            && $targetUser->role->slug_identifier !== 'admin';
    }

    /*
     * Determine whether the user can access the reactivation confirmation prompt.
     */
    public function reactivateConfirm(User $user, User $targetUser): bool
    {
        return $user->role->slug_identifier === 'admin'
            && $targetUser->role->slug_identifier !== 'admin';
    }

    /*
     * Determine whether the user can reactivate an inactive user account.
     */
    public function reactivate(User $user, User $targetUser): bool
    {
        return $user->role->slug_identifier === 'admin'
            && $targetUser->role->slug_identifier !== 'admin';
    }

    // --- MOBILE APP PERMISSIONS ---

    /*
     * Determine whether the user can retrieve their own profile information.
     */
    public function viewProfile(User $user, User $targetUser): bool
    {
        return $user->is($targetUser)
            && in_array($user->role->slug_identifier, ['admin', 'cwd_officer', 'supervisor', 'field_personnel'], true);
    }

    /*
     * Determine whether the user has supervisor privileges to access the mobile dashboard.
     */
    public function viewSupervisorDashboard(User $user): bool
    {
        return $user->role->slug_identifier === 'supervisor';
    }
}
