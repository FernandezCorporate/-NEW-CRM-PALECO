<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/*
 * Defines RBAC (Role-based access controls) for each available action on a ticket remark resource.
 */
class TicketRemarkPolicy
{
    // Web app permissions
    public function create(User $user, Ticket $ticket): bool 
    { 
        return $user->role->slug_identifier === 'cwd_officer'; 
    }

    // Mobile app permissions
    public function mobileCreate(User $user, Ticket $ticket): bool 
    {
        if ($user->role->slug_identifier === 'foreman') {
            return $user->department_id === $ticket->department_id;
        }

        if ($user->role->slug_identifier === 'field_personnel') {
            return $user->teams()->where('teams.id', $ticket->team_id)->exists();
        }

        return false;
    }

    /*
     * Web-app
     * create       => CWD Officer only; allows adding a chronological communication remark to a ticket.
     * 
     * Mobile app
     * mobileCreate => Foreman (if assigned to the same department) and Field Personnel (if assigned to the same team); allows adding a chronological communication remark to a ticket.
     */
}