<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/*
 * Defines role-based access controls for service ticket lifecycle operations across web and mobile surfaces.
 */
class TicketPolicy
{
    // --- WEB CWD & ADMIN PERMISSIONS ---

    /*
     * Determine whether the user can browse global ticket registries in the web app.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role->slug_identifier, ['cwd_officer', 'admin'], true);
    }

    /*
     * Determine whether the user can inspect detailed ticket profiles in the web app.
     */
    public function webView(User $user, Ticket $ticket): Response
    {
        return in_array($user->role->slug_identifier, ['cwd_officer', 'admin'], true)
            ? Response::allow()
            : Response::denyAsNotFound('The requested ticket was not found.');
    }

    /*
     * Determine whether the user can access the initial complaint registration form.
     */
    public function ticketForm(User $user): bool
    {
        return $user->role->slug_identifier === 'cwd_officer';
    }

    /*
     * Determine whether the user can register a new service ticket.
     */
    public function create(User $user): bool
    {
        return $user->role->slug_identifier === 'cwd_officer';
    }

    /*
     * Determine whether the user can spawn and attach a manual child ticket.
     */
    public function createChild(User $user, Ticket $ticket): Response
    {
        if ($user->role->slug_identifier !== 'cwd_officer') {
            return Response::deny('This action is unauthorized.');
        }

        return ! $ticket->trashed()
            ? Response::allow()
            : Response::denyAsNotFound('The requested ticket was not found.');
    }

    // --- MOBILE SUPERVISOR & FIELD PERSONNEL PERMISSIONS ---

    /*
     * Determine whether the user can view the mobile inbox scoped to their role.
     */
    public function viewInbox(User $user): bool
    {
        return in_array($user->role->slug_identifier, ['supervisor', 'field_personnel'], true);
    }

    /*
     * Determine whether the supervisor can assign or reassign the ticket to a field team.
     */
    public function assign(User $user, Ticket $ticket): Response
    {
        if ($user->role->slug_identifier !== 'supervisor') {
            return Response::deny('Only supervisors can assign tickets.');
        }

        return $user->department_id === $ticket->department_id
            ? Response::allow()
            : Response::denyAsNotFound('The requested ticket was not found.');
    }

    /*
     * Determine whether the user can view ticket details (role-scoped).
     */
    public function view(User $user, Ticket $ticket): Response
    {
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

        return in_array($user->role->slug_identifier, ['cwd_officer', 'admin'], true)
            ? Response::allow()
            : Response::denyAsNotFound('The requested ticket was not found.');
    }

    /*
     * Determine whether field personnel can start work on an assigned ticket.
     */
    public function start(User $user, Ticket $ticket): Response
    {
        if ($user->role->slug_identifier !== 'field_personnel') {
            return Response::deny('Only field personnel can start tickets.');
        }

        return $user->teams()->where('teams.id', $ticket->team_id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound('The requested ticket was not found.');
    }

    /*
     * Determine whether field personnel can submit an accomplishment report for a ticket.
     */
    public function accomplish(User $user, Ticket $ticket): Response
    {
        if ($user->role->slug_identifier !== 'field_personnel') {
            return Response::deny('Only field personnel can submit accomplishments for tickets.');
        }

        return $user->teams()->where('teams.id', $ticket->team_id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound('The requested ticket was not found.');
    }

    /*
     * Determine whether a supervisor can verify or reject an accomplishment report.
     */
    public function verify(User $user, Ticket $ticket): Response
    {
        if ($user->role->slug_identifier !== 'supervisor') {
            return Response::deny('Only supervisors can verify accomplishments.');
        }

        return $user->department_id === $ticket->department_id
            ? Response::allow()
            : Response::denyAsNotFound('The requested ticket was not found.');
    }

    /*
     * Determine whether a supervisor can initiate an endorsement transfer for the ticket.
     */
    public function endorse(User $user, Ticket $ticket): Response
    {
        if ($user->role->slug_identifier !== 'supervisor') {
            return Response::deny('Only supervisors can endorse tickets.');
        }

        return $user->department_id === $ticket->department_id
            ? Response::allow()
            : Response::denyAsNotFound('The requested ticket was not found.');
    }

    /*
     * Determine whether a supervisor can inspect assignment and endorsement history.
     */
    public function viewHistory(User $user, Ticket $ticket): Response
    {
        if ($user->role->slug_identifier !== 'supervisor') {
            return Response::deny('Only supervisors can view ticket history.');
        }

        return $user->department_id === $ticket->department_id
            ? Response::allow()
            : Response::denyAsNotFound('The requested ticket was not found.');
    }
}
