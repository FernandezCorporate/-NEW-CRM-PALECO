<?php

namespace App\Services\Push;

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Events\TeamUpdated;
use App\Events\TicketAccomplished;
use App\Events\TicketAssigned;
use App\Events\TicketCancelled;
use App\Events\TicketCreated;
use App\Events\TicketEndorsementDecided;
use App\Events\TicketEndorsementInitiated;
use App\Events\TicketForceClosed;
use App\Events\TicketRemarkAdded;
use App\Events\TicketReportRejected;
use App\Events\TicketVerifiedAndClosed;
use App\Events\TicketWorkStarted;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decides who is notified about a ticket event and what the notification says.
 * Only active mobile accounts that can act on the event are notified, never the person who caused it,
 * and no consumer details (name, contact number, address) are ever placed in a notification.
 */
class PushMessageFactory
{
    /**
     * @return array<int, PushMessage>
     */
    public function forEvent(object $event, ?string $actorId = null): array
    {
        $messages = match (true) {
            $event instanceof TicketCreated => $this->ticketCreated($event),
            $event instanceof TicketAccomplished => $this->reportSubmitted($event),
            $event instanceof TicketEndorsementInitiated => $this->ticketOnHold($event),
            $event instanceof TicketEndorsementDecided => $this->endorsementDecided($event),
            $event instanceof TicketWorkStarted => $this->workStarted($event),
            $event instanceof TicketAssigned => $this->ticketAssigned($event),
            $event instanceof TicketCancelled => $this->ticketStopped($event->ticket, $event->ticket->team_id, 'cancelled'),
            $event instanceof TicketForceClosed => $this->ticketStopped($event->ticket, $event->targetTeamId, 'force-closed'),
            $event instanceof TicketReportRejected => $this->reportReturned($event),
            $event instanceof TicketVerifiedAndClosed => $this->ticketClosed($event),
            $event instanceof TicketRemarkAdded => $this->remarkAdded($event),
            $event instanceof TeamUpdated => $this->teamMembership($event),
            default => [],
        };

        return array_values(array_filter($messages, fn (PushMessage $message) => $message->userId !== $actorId));
    }

    // --- EVENTS ---

    private function ticketCreated(TicketCreated $event): array
    {
        $ticket = $event->ticket;

        return $this->toUsers(
            $this->supervisorsOf($ticket->department_id, ['tickets.assign']),
            'ticket_created', 'New ticket in your department', $this->summary($ticket), $ticket
        );
    }

    private function reportSubmitted(TicketAccomplished $event): array
    {
        $ticket = $event->ticket;

        return $this->toUsers(
            $this->supervisorsOf($ticket->department_id, ['reports.verify']),
            'report_submitted', 'Report waiting for your verification', $this->summary($ticket, withBarangay: false), $ticket
        );
    }

    private function endorsementDecided(TicketEndorsementDecided $event): array
    {
        $endorsement = $event->endorsement;
        $ticket = $endorsement->ticket;

        if (! $ticket) {
            return [];
        }

        $approved = $endorsement->status === EndorsementStatus::APPROVED;
        $body = $approved
            ? "{$ticket->ticket_number} was moved to ".($endorsement->newDepartment?->dept_name ?? 'another department').'.'
            : "{$ticket->ticket_number} was returned to your department.";

        $supervisors = $this->toUsers(
            $this->supervisorsOf($endorsement->old_department_id ?? $ticket->department_id, ['tickets.assign']),
            'endorsement_decided', $approved ? 'Endorsement approved' : 'Endorsement rejected', $body, $ticket
        );

        return [...$supervisors, ...($approved ? $this->crewTransferred($event, $ticket) : $this->crewResumes($ticket))];
    }

    /**
     * A supervisor asked to move the ticket to another department: it is frozen, so the crew must not start.
     */
    private function ticketOnHold(TicketEndorsementInitiated $event): array
    {
        $ticket = $event->endorsement->ticket;

        if (! $ticket) {
            return [];
        }

        return $this->toUsers(
            $this->crewOf($ticket->team_id, ['tickets.start']),
            'ticket_on_hold', 'Ticket on hold', "{$ticket->ticket_number} is waiting for a transfer decision. Don't start work yet.", $ticket,
            PushMessage::CHANNEL_DISPATCH, urgent: true
        );
    }

    /**
     * Approved transfer: the crew that had the ticket loses it, which the app already handles as "reassigned away".
     */
    private function crewTransferred(TicketEndorsementDecided $event, Ticket $ticket): array
    {
        $department = $event->endorsement->newDepartment?->dept_name ?? 'another department';

        return $this->toUsers(
            $this->crewOf($event->previousTeamId, ['tickets.start']),
            'ticket_reassigned_away', 'Ticket transferred', "{$ticket->ticket_number} was moved to {$department}. It is no longer assigned to your crew.", $ticket
        );
    }

    /**
     * Rejected transfer: the ticket goes back to its crew, who may now start work again.
     */
    private function crewResumes(Ticket $ticket): array
    {
        if ($ticket->status !== TicketStatus::ASSIGNED) {
            return [];
        }

        return $this->toUsers(
            $this->crewOf($ticket->team_id, ['tickets.start']),
            'ticket_resumed', 'Ticket back with your crew', "{$ticket->ticket_number} was not transferred. You can start work.", $ticket,
            PushMessage::CHANNEL_DISPATCH, urgent: true
        );
    }

    /**
     * A crew started work: the department's supervisors can follow it up.
     */
    private function workStarted(TicketWorkStarted $event): array
    {
        $ticket = $event->ticket;
        $crew = $ticket->team?->team_name ?? 'The crew';

        return $this->toUsers(
            $this->supervisorsOf($ticket->department_id, ['tickets.assign', 'reports.verify']),
            'work_started', 'Work started', "{$crew} started work on {$ticket->ticket_number}.", $ticket
        );
    }

    private function ticketAssigned(TicketAssigned $event): array
    {
        $ticket = $event->ticket;

        $messages = $this->toUsers(
            $this->crewOf($ticket->team_id, ['tickets.start']),
            'ticket_assigned', 'New dispatch', $this->summary($ticket), $ticket,
            PushMessage::CHANNEL_DISPATCH, urgent: true
        );

        if ($event->previousTeamId && $event->previousTeamId !== $ticket->team_id) {
            $messages = [...$messages, ...$this->toUsers(
                $this->crewOf($event->previousTeamId, ['tickets.start']),
                'ticket_reassigned_away', 'Ticket reassigned', "{$ticket->ticket_number} was reassigned to another crew.", $ticket
            )];
        }

        return $messages;
    }

    private function ticketStopped(Ticket $ticket, ?string $teamId, string $how): array
    {
        $body = "{$ticket->ticket_number} was {$how} by the CWD.";

        // The crew must stop working right away, so their alert is urgent
        $crew = $this->toUsers(
            $this->crewOf($teamId, ['tickets.start', 'tickets.accomplish']),
            'ticket_stopped', 'Stop work', $body, $ticket,
            PushMessage::CHANNEL_DISPATCH, urgent: true
        );

        $supervisors = $this->toUsers(
            $this->supervisorsOf($ticket->department_id, ['tickets.assign']),
            'ticket_stopped', 'Ticket '.$how, $body, $ticket
        );

        return [...$crew, ...$supervisors];
    }

    private function reportReturned(TicketReportRejected $event): array
    {
        $ticket = $event->ticket;

        return $this->toUsers(
            $this->crewOf($ticket->team_id, ['tickets.accomplish']),
            'report_returned', 'Report returned', "Your report for {$ticket->ticket_number} was returned. Please resubmit it.", $ticket,
            PushMessage::CHANNEL_DISPATCH, urgent: true
        );
    }

    private function ticketClosed(TicketVerifiedAndClosed $event): array
    {
        $ticket = $event->ticket;

        return $this->toUsers(
            $this->crewOf($ticket->team_id, ['tickets.accomplish']),
            'ticket_closed', 'Ticket closed', "{$ticket->ticket_number} was verified and closed.", $ticket
        );
    }

    private function remarkAdded(TicketRemarkAdded $event): array
    {
        $remark = $event->remark;
        $ticket = $remark->ticket;

        // Internal notes are only for CWD officers
        if ($remark->is_internal || ! $ticket) {
            return [];
        }

        $recipients = $this->crewOf($ticket->team_id, ['tickets.remark'])
            ->merge($this->supervisorsOf($ticket->department_id, ['tickets.remark']))
            ->unique('id')
            ->reject(fn (User $user) => $user->id === $remark->user_id);

        // The remark text itself stays in the app: it may mention the consumer
        return $this->toUsers($recipients, 'remark_added', "New remark on {$ticket->ticket_number}", 'Open the ticket to read the new remark.', $ticket);
    }

    private function teamMembership(TeamUpdated $event): array
    {
        $team = $event->team;
        $messages = [];

        foreach (['added' => $event->addedMemberIds, 'removed' => $event->removedMemberIds] as $change => $userIds) {
            if (! $userIds) {
                continue;
            }

            $members = $this->activeMobileUsers()->whereIn('id', $userIds)->get();
            $title = $change === 'added' ? 'Added to a team' : 'Removed from a team';
            $body = $change === 'added' ? "You were added to {$team->team_name}." : "You were removed from {$team->team_name}.";

            $messages = [...$messages, ...$this->toUsers($members, 'team_membership', $title, $body, null)];
        }

        return $messages;
    }

    // --- RECIPIENTS ---

    /**
     * Active supervisors of a department who hold at least one of the permissions.
     */
    private function supervisorsOf(?int $departmentId, array $permissions): Collection
    {
        if (! $departmentId) {
            return collect();
        }

        return $this->activeMobileUsers()
            ->where('department_id', $departmentId)
            ->whereHas('role', fn ($q) => $q->where('slug_identifier', 'supervisor'))
            ->whereHas('permissions', fn ($q) => $q->whereIn('name', $permissions))
            ->get();
    }

    /**
     * Active members of a crew who hold at least one of the permissions.
     */
    private function crewOf(?string $teamId, array $permissions): Collection
    {
        if (! $teamId) {
            return collect();
        }

        return $this->activeMobileUsers()
            ->whereHas('role', fn ($q) => $q->where('slug_identifier', 'field_personnel'))
            ->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId))
            ->whereHas('permissions', fn ($q) => $q->whereIn('name', $permissions))
            ->get();
    }

    /**
     * Active accounts with at least one phone whose login hasn't expired; only those phones are loaded.
     */
    private function activeMobileUsers()
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('pushDevices', fn ($q) => $q->withLiveLogin())
            ->with(['pushDevices' => fn ($q) => $q->withLiveLogin()]);
    }

    // --- MESSAGES ---

    /**
     * One message per registered phone of each recipient.
     */
    private function toUsers(Collection $users, string $type, string $title, string $body, ?Ticket $ticket, string $channel = PushMessage::CHANNEL_UPDATES, bool $urgent = false): array
    {
        $data = $ticket ? ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number] : ['ticket_id' => '', 'ticket_number' => ''];

        return $users->flatMap(fn (User $user) => $user->pushDevices->map(fn ($device) => new PushMessage(
            token: $device->fcm_token,
            type: $type,
            title: $title,
            body: $body,
            data: $data,
            channel: $channel,
            urgent: $urgent,
            tag: $ticket ? 'ticket-'.$ticket->id : null,
            userId: $user->id,
        )))->values()->all();
    }

    /**
     * "TKT-261104-001 · Power Interruption · Brgy. San Pedro": no consumer details.
     */
    private function summary(Ticket $ticket, bool $withBarangay = true): string
    {
        $nature = $ticket->other_category || ! $ticket->category
            ? ($ticket->other_category_name ?: 'Unspecified complaint')
            : $ticket->category->category_name;

        $parts = [$ticket->ticket_number, $nature];

        if ($withBarangay && $ticket->barangay) {
            $parts[] = 'Brgy. '.$ticket->barangay;
        }

        return implode(' · ', $parts);
    }
}
