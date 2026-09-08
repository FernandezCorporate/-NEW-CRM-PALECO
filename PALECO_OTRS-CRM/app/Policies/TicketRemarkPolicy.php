<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/*
 * Defines RBAC (Role-based access controls) for each available action on a ticket remark resource.
 */
class TicketRemarkPolicy
{
    // Web & Mobile app permissions
    public function create(User $user, Ticket $ticket): bool { return in_array($user->role->slug_identifier, ['cwd_officer', 'foreman', 'field_personnel']); }

    /*
     * Web & Mobile app
     * create => CWD Officer, Foreman, and Field Personnel only; allows adding a chronological communication remark to a ticket.
     */
}