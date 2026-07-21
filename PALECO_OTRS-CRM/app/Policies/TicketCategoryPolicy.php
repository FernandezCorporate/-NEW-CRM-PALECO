<?php

namespace App\Policies;

use App\Models\User;

class TicketCategoryPolicy
{
    public function viewAny(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function ticketCategoryForm(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function create(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function view(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function update(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function deleteConfirm(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function archive(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function restore(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function forceDelete(User $user): bool { return $user->role->slug_identifier === 'admin'; }

    /*
     * viewAny: Determines if the user can view the list of ticket categories.
     * ticketCategoryForm: Determines if the user can access the ticket category creation or edit forms.
     * create: Determines if the user can create new ticket categories.
     * view: Determines if the user can view a specific ticket category's details.
     * update: Determines if the user can update an existing ticket category.
     * deleteConfirm: Determines if the user can access the deletion confirmation prompt.
     * archive: Determines if the user can soft-delete (archive) a ticket category.
     * restore: Determines if the user can restore a previously archived ticket category.
     * forceDelete: Determines if the user can permanently delete a ticket category.
     */
}