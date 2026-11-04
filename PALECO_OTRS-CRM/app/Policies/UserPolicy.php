<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

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
        return $user->hasRole('admin') && $user->hasPermission('users.view');
    }

    /*
     * Determine whether the user can access user account creation or modification forms.
     */
    public function userForm(User $user, ?User $targetUser = null): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        if ($targetUser && $targetUser->exists) {
            return $user->hasPermission('users.update')
                && (! $targetUser->hasRole('admin') || $user->is($targetUser));
        }

        return $user->hasPermission('users.create');
    }

    /*
     * Determine whether the user can view a specific user account's profile details.
     */
    public function view(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('users.view');
    }

    /*
     * Determine whether the user can create new user accounts.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('users.create');
    }

    /*
     * Determine whether the user can update an existing user account profile.
     */
    public function update(User $user, User $targetUser): bool
    {
        return $user->hasRole('admin')
            && $user->hasPermission('users.update')
            && (! $targetUser->hasRole('admin') || $user->is($targetUser));
    }

    /*
     * Determine whether the user can reset another user account's password.
     */
    public function resetPassword(User $user, User $targetUser): bool
    {
        return $this->update($user, $targetUser);
    }

    /*
     * Determine whether the user can administratively unlock a locked user account.
     */
    public function unlock(User $user, User $targetUser): bool
    {
        return $this->update($user, $targetUser);
    }

    /*
     * Determine whether the user can access the deactivation confirmation prompt.
     */
    public function deactivateConfirm(User $user, User $targetUser): bool
    {
        return $user->hasRole('admin')
            && $user->hasPermission('users.deactivate')
            && ! $targetUser->hasRole('admin');
    }

    /*
     * Determine whether the user can deactivate a user account.
     */
    public function deactivate(User $user, User $targetUser): bool
    {
        return $user->hasRole('admin')
            && $user->hasPermission('users.deactivate')
            && ! $targetUser->hasRole('admin');
    }

    /*
     * Determine whether the user can access the reactivation confirmation prompt.
     */
    public function reactivateConfirm(User $user, User $targetUser): bool
    {
        return $user->hasRole('admin')
            && $user->hasPermission('users.deactivate')
            && ! $targetUser->hasRole('admin');
    }

    /*
     * Determine whether the user can reactivate an inactive user account.
     */
    public function reactivate(User $user, User $targetUser): bool
    {
        return $user->hasRole('admin')
            && $user->hasPermission('users.deactivate')
            && ! $targetUser->hasRole('admin');
    }

    /**
     * Determine whether the user can set the permissions of an account (null while creating one).
     * An admin cannot change their own permissions or another admin's: an admin's checklist is set once, at creation.
     */
    public function managePermissions(User $user, ?User $targetUser = null): bool
    {
        if (! $user->hasRole('admin') || ! $user->hasPermission('users.permissions')) {
            return false;
        }

        return ! $targetUser || ! $targetUser->exists || ! $targetUser->hasRole('admin');
    }

    // --- MOBILE APP PERMISSIONS ---

    /*
     * Determine whether the user can retrieve their own profile information.
     */
    public function viewProfile(User $user, User $targetUser): Response
    {
        return $user->is($targetUser) && $user->hasRole('admin', 'cwd_officer', 'supervisor', 'field_personnel')
            ? Response::allow()
            : Response::denyAsNotFound('The requested user profile was not found.');
    }

    /*
     * Determine whether the user has supervisor privileges to access the mobile dashboard.
     */
    public function viewSupervisorDashboard(User $user): bool
    {
        return $user->hasRole('supervisor');
    }
}
