<?php

namespace App\Policies;

use App\Models\User;

/*
 * Defines role-based access controls for managing complaint and service ticket categories.
 */
class TicketCategoryPolicy
{
    /*
     * Determine whether the user can browse ticket category listings.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('categories.view');
    }

    /*
     * Determine whether the user can access the category creation or edit form.
     */
    public function ticketCategoryForm(User $user): bool
    {
        return $user->hasRole('admin') && ($user->hasPermission('categories.create') || $user->hasPermission('categories.update'));
    }

    /*
     * Determine whether the user can create new ticket categories.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('categories.create');
    }

    /*
     * Determine whether the user can view detailed ticket category metrics.
     */
    public function view(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('categories.view');
    }

    /*
     * Determine whether the user can modify an existing ticket category.
     */
    public function update(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('categories.update');
    }

    /*
     * Determine whether the user can view the delete/archive confirmation prompt.
     */
    public function deleteConfirm(User $user): bool
    {
        // The same page confirms archiving and permanent deletion
        return $user->hasRole('admin') && ($user->hasPermission('categories.archive') || $user->hasPermission('categories.delete'));
    }

    /*
     * Determine whether the user can soft-delete (archive) a ticket category.
     */
    public function archive(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('categories.archive');
    }

    /*
     * Determine whether the user can restore an archived ticket category.
     */
    public function restore(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('categories.archive');
    }

    /*
     * Determine whether the user can permanently purge a ticket category.
     */
    public function forceDelete(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('categories.delete');
    }
}
