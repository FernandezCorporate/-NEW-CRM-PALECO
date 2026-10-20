<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Assigns or reassigns a service ticket to a designated field team.
 */
class AssignTicket
{
    /**
     * @throws ValidationException
     */
    public function handle(Ticket $ticket, string $teamId, User $assigner, ?string $reason = null): Ticket
    {
        return $this->execute($ticket, $teamId, $assigner, $reason);
    }

    /**
     * @throws ValidationException
     */
    public function execute(Ticket $ticket, string $teamId, User $assigner, ?string $reason = null): Ticket
    {
        $allowedStatuses = [
            TicketStatus::OPEN,
            TicketStatus::ASSIGNED,
        ];

        $team = Team::where('id', $teamId)
            ->whereNull('deleted_at')
            ->first();

        if (! $team || ($ticket->department_id && $team->department_id !== $ticket->department_id)) {
            throw ValidationException::withMessages([
                'team_id' => 'The selected team is invalid, archived, or does not belong to the ticket\'s department.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $team, $teamId, $assigner, $reason, $allowedStatuses) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Waits for any roster edit or archive on this team to finish before assigning to it
            $lockedTeam = Team::withTrashed()
                ->where('id', $team->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedTeam || $lockedTeam->trashed()) {
                throw ValidationException::withMessages([
                    'team_id' => 'The selected team was archived while you were assigning it.',
                ]);
            }

            if (! in_array($lockedTicket->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Tickets that are in-progress, resolved, closed, or pending endorsement cannot be assigned or reassigned.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::PENDING_ENDORSEMENT) {
                throw ValidationException::withMessages([
                    'status' => 'Ticket is locked pending inter-departmental endorsement review.',
                ]);
            }

            if ($lockedTicket->department_id && $team->department_id !== $lockedTicket->department_id) {
                throw ValidationException::withMessages([
                    'team_id' => 'The selected team does not belong to the ticket\'s department (the ticket may have been re-routed concurrently).',
                ]);
            }

            $oldStatus = $lockedTicket->status;

            // Close active assignment history record
            $lockedTicket->assignments()
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            TicketAssignment::create([
                'ticket_id' => $lockedTicket->id,
                'team_id' => $teamId,
                'assigned_by_id' => $assigner->id,
                'reason' => $reason,
            ]);

            if ($oldStatus !== TicketStatus::ASSIGNED) {
                TicketStatusLog::create([
                    'ticket_id' => $lockedTicket->id,
                    'old_status' => $oldStatus,
                    'new_status' => TicketStatus::ASSIGNED,
                    'changed_by_id' => $assigner->id,
                ]);
            }

            $previousTeamId = $lockedTicket->team_id;

            $lockedTicket->update([
                'team_id' => $teamId,
                'status' => TicketStatus::ASSIGNED,
            ]);

            $freshTicket = $lockedTicket->fresh([
                'category',
                'team',
                'department',
                'activeAssignment.team',
                'activeAssignment.assigner.role',
            ]);

            DB::afterCommit(function () use ($freshTicket, $previousTeamId) {
                rescue(fn () => TicketAssigned::dispatch($freshTicket, $previousTeamId), report: true);
            });

            return $freshTicket;
        });
    }
}

