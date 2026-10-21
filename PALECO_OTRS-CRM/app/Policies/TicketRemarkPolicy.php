<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/*
 * Defines role-based access controls for authoring ticket remarks across web and mobile platforms.
 */
class TicketRemarkPolicy
{
    /*
     * Determine whether the web user can post a remark on a ticket timeline.
     */
    public function create(User $user, Ticket $ticket): Response
    {
        if ($user->role->slug_identifier !== 'cwd_officer') {
            return Response::deny('This action is unauthorized.');
        }

        return $this->isReadOnly($ticket)
            ? Response::deny('Closed and cancelled tickets are read-only. Remarks can no longer be added.')
            : Response::allow();
    }

    /*
     * Determine whether the mobile user can post a public remark on a ticket.
     */
    public function mobileCreate(User $user, Ticket $ticket): Response
    {
        if ($this->isReadOnly($ticket)) {
            return Response::deny('Closed and cancelled tickets are read-only. Remarks can no longer be added.');
        }

        if ($user->role->slug_identifier === 'supervisor') {
            return $user->department_id === $ticket->department_id
                ? Response::allow()
                : Response::denyAsNotFound('The requested ticket was not found.');
        }

        if ($user->role->slug_identifier === 'field_personnel') {
            return $user->teams()->where('teams.id', $ticket->team_id)->exists()
                ? Response::allow()
                : Response::denyAsNotFound('The requested ticket was not found.');
        }

        return Response::deny('This action is unauthorized.');
    }

    /*
     * Closed and cancelled tickets are read-only.
     */
    private function isReadOnly(Ticket $ticket): bool
    {
        return in_array($ticket->status, [TicketStatus::CLOSED, TicketStatus::CANCELLED], true);
    }
}
