<?php

namespace App\Policies;

use App\Models\User;

/*
 * Defines access control rules for viewing and evaluating ticket accomplishment reports.
 */
class TicketAccomplishmentPolicy
{
    /*
     * Determine whether the user can inspect an accomplishment report in the web portal.
     */
    public function view(User $user): bool
    {
        return $user->role->slug_identifier === 'cwd_officer';
    }
}
