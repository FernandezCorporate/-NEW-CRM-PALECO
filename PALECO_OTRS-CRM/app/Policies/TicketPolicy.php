<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Ticket;

class TicketPolicy
{
    public function viewAny(User $user): bool { return in_array($user->role->slug_identifier, ['cwd_officer', 'admin'], true); }
    public function ticketForm(User $user): bool { return $user->role->slug_identifier === 'cwd_officer'; }

    public function viewInbox(User $user): bool { return in_array($user->role->slug_identifier, ['foreman', 'field_personnel']); }
    public function assign(User $user, Ticket $ticket): bool { return $user->role->slug_identifier === 'foreman' && $user->department_id === $ticket->department_id; }

    public function start(User $user, Ticket $ticket): bool { return $user->role->slug_identifier === 'field_personnel' && $user->teams()->where('teams.id', $ticket->team_id)->exists(); }
    public function accomplish(User $user, Ticket $ticket): bool { return $user->role->slug_identifier === 'field_personnel' && $user->teams()->where('teams.id', $ticket->team_id)->exists(); }

    /* WEB APP POLICIES
     * viewAny: Determines if the web app user can view the primary list of tickets.
     * ticketForm: Determines if the user can access the form to create or manage a ticket.
     */

    /* MOBILE APP POLICIES
     * viewInbox: Determines if the mobile app user can view the primary list of tickets.
     * assign: Determines if the user can assign or reassign a ticket.
     */
}