<?php

namespace App\Policies;

use App\Enums\TicketStatus;
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

        if ($ticket->trashed()) {
            return Response::denyAsNotFound('The requested ticket was not found.');
        }

        if (! is_null($ticket->parent_ticket_id)) {
            return Response::deny('Subordinate child tickets cannot spawn further child sub-tasks. Only root origin tickets can spawn child tickets.');
        }

        if ($ticket->status === TicketStatus::PENDING_ENDORSEMENT) {
            return Response::deny('Cannot spawn child tickets from a ticket that is pending endorsement.');
        }

        if ($ticket->status === TicketStatus::CANCELLED) {
            return Response::deny('Cannot spawn child tickets from a cancelled ticket.');
        }

        if ($ticket->status === TicketStatus::CLOSED) {
            return Response::deny('Cannot spawn child tickets from a closed ticket.');
        }

        return Response::allow();
    }

    /*
     * Determine whether the user can post an operational remark on this ticket.
     */
    public function createRemark(User $user, Ticket $ticket): bool
    {
        return $user->role->slug_identifier === 'cwd_officer';
    }

    /*
     * Determine whether the user can modify clerical intake details for this ticket.
     * Permitted strictly to CWD officers and only while the ticket is in OPEN status.
     */
    public function update(User $user, Ticket $ticket): Response
    {
        if ($user->role->slug_identifier !== 'cwd_officer') {
            return Response::deny('Only CWD officers can edit ticket intake details.');
        }

        if ($ticket->trashed()) {
            return Response::denyAsNotFound('The requested ticket was not found.');
        }

        if ($ticket->status !== TicketStatus::OPEN) {
            return Response::deny('Ticket intake details can only be edited while in Open status. Once assigned to a team or in progress, details are locked to prevent operational dispatch conflicts.');
        }

        return Response::allow();
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

        if ($ticket->status === TicketStatus::PENDING_ENDORSEMENT) {
            return Response::deny('Ticket is locked pending inter-departmental endorsement review.');
        }

        if ($ticket->status === TicketStatus::CANCELLED) {
            return Response::deny('Cancelled tickets cannot be assigned.');
        }

        if (in_array($ticket->status, [TicketStatus::IN_PROGRESS, TicketStatus::RESOLVED, TicketStatus::CLOSED], true)) {
            return Response::deny('Tickets that are in-progress, resolved, or closed cannot be assigned or reassigned.');
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

        if ($ticket->status === TicketStatus::PENDING_ENDORSEMENT) {
            return Response::deny('Ticket is locked pending inter-departmental endorsement review.');
        }

        if ($ticket->status === TicketStatus::CANCELLED) {
            return Response::deny('Cancelled tickets cannot be started.');
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

        if ($ticket->status === TicketStatus::PENDING_ENDORSEMENT) {
            return Response::deny('Ticket is locked pending inter-departmental endorsement review.');
        }

        if ($ticket->status === TicketStatus::CANCELLED) {
            return Response::deny('Cannot submit accomplishments for a cancelled ticket.');
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

        if ($ticket->status === TicketStatus::CANCELLED) {
            return Response::deny('Cancelled tickets cannot be endorsed.');
        }

        if (! is_null($ticket->parent_ticket_id)) {
            return Response::deny('Child tickets cannot be endorsed. Ask the CWD officer to create a new child ticket from the origin ticket instead.');
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

    /*
     * Determine whether the user can cancel the service ticket.
     * Permitted strictly to CWD officers and only while the ticket is in OPEN status.
     */
    public function cancel(User $user, Ticket $ticket): Response
    {
        if ($user->role->slug_identifier !== 'cwd_officer') {
            return Response::deny('Only CWD officers can cancel tickets.');
        }

        if ($ticket->trashed()) {
            return Response::denyAsNotFound('The requested ticket was not found.');
        }

        if ($ticket->status === TicketStatus::CANCELLED) {
            return Response::deny('This ticket has already been cancelled.');
        }

        if ($ticket->status !== TicketStatus::OPEN) {
            return Response::deny('Ticket can only be cancelled while in Open status. Once assigned to a team or dispatched, cancellation is locked.');
        }

        return Response::allow();
    }

    /*
     * Determine whether the user can administratively force-close the service ticket.
     * Permitted strictly to CWD officers.
     * Allowed strictly from ASSIGNED, IN_PROGRESS, or ENDORSED status.
     * Forbidden when OPEN (must cancel), PENDING_ENDORSEMENT (in-flight review locked),
     * or RESOLVED (accomplishment report awaiting supervisor verification).
     */
    public function forceClose(User $user, Ticket $ticket): Response
    {
        if ($ticket->trashed()) {
            return Response::denyAsNotFound('The requested ticket was not found.');
        }

        if ($user->role->slug_identifier !== 'cwd_officer') {
            return Response::deny('Only CWD officers are authorized to force-close tickets.');
        }

        if ($ticket->status === TicketStatus::CLOSED) {
            return Response::deny('This ticket is already closed.');
        }

        if ($ticket->status === TicketStatus::CANCELLED) {
            return Response::deny('Cancelled tickets cannot be force-closed.');
        }

        if ($ticket->status === TicketStatus::OPEN) {
            return Response::deny('Open tickets must be cancelled via the cancellation workflow, not force-closed.');
        }

        if ($ticket->status === TicketStatus::PENDING_ENDORSEMENT) {
            return Response::deny('Tickets pending endorsement review cannot be force-closed. Complete the transfer decision first.');
        }

        if ($ticket->status === TicketStatus::RESOLVED) {
            return Response::deny('Resolved tickets are awaiting supervisor verification of the accomplishment report and cannot be force-closed.');
        }

        $allowedStatuses = [
            TicketStatus::ASSIGNED,
            TicketStatus::IN_PROGRESS,
            TicketStatus::ENDORSED,
        ];

        if (! in_array($ticket->status, $allowedStatuses, true)) {
            return Response::deny('Ticket cannot be force-closed from its current status.');
        }

        return Response::allow();
    }
}
