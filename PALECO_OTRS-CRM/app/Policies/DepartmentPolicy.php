<?php

namespace App\Policies;

use App\Models\User;

/*
 * Defines role-based access controls for operations on Department resources.
 */
class DepartmentPolicy
{
    /*
     * Determine whether the user can browse department listings.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('departments.view');
    }

    /*
     * Determine whether the user can view a specific department profile.
     */
    public function view(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('departments.view');
    }

    /*
     * Determine whether the user can access the department creation or edit form.
     */
    public function departmentForm(User $user): bool
    {
        return $user->hasRole('admin') && ($user->hasPermission('departments.create') || $user->hasPermission('departments.update'));
    }

    /*
     * Determine whether the user can create new departments.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('departments.create');
    }

    /*
     * Determine whether the user can update an existing department.
     */
    public function update(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('departments.update');
    }

    /*
     * Determine whether the user can access the deletion confirmation prompt.
     */
    public function deleteConfirm(User $user): bool
    {
        // The same page confirms archiving and permanent deletion
        return $user->hasRole('admin') && ($user->hasPermission('departments.archive') || $user->hasPermission('departments.delete'));
    }

    /*
     * Determine whether the user can soft-delete (archive) a department.
     */
    public function archive(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('departments.archive');
    }

    /*
     * Determine whether the user can restore an archived department.
     */
    public function restore(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('departments.archive');
    }

    /*
     * Determine whether the user can permanently purge a department record.
     */
    public function forceDelete(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('departments.delete');
    }
}
