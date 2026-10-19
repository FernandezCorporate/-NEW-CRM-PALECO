<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Events\TicketForceClosed;
use App\Models\Ticket;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Administratively force-closes an active service ticket.
 */
class ForceCloseTicket
{
    /**
     * @throws ValidationException
     */
    public function handle(Ticket $ticket, array $data, User $actor): Ticket
    {
        return $this->execute($ticket, $data, $actor);
    }

    /**
     * @throws ValidationException
     */
    public function execute(Ticket $ticket, array $data, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($actor->role->slug_identifier !== 'cwd_officer') {
                throw ValidationException::withMessages([
                    'actor' => 'Only CWD Officers are authorized to force-close tickets.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::CLOSED) {
                throw ValidationException::withMessages([
                    'status' => 'This ticket is already closed.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'Cancelled tickets cannot be force-closed.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::OPEN) {
                throw ValidationException::withMessages([
                    'status' => 'Open tickets must be cancelled via the cancellation workflow, not force-closed.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::PENDING_ENDORSEMENT) {
                throw ValidationException::withMessages([
                    'status' => 'Tickets pending endorsement review cannot be force-closed. Complete the transfer decision first.',
                ]);
            }

            $allowedStatuses = [
                TicketStatus::ASSIGNED,
                TicketStatus::IN_PROGRESS,
                TicketStatus::RESOLVED,
                TicketStatus::ENDORSED,
            ];

            if (! in_array($lockedTicket->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Ticket cannot be force-closed from its current status (' . $lockedTicket->status->label() . ').',
                ]);
            }

            // Subordinate child tickets check: require ALL child tickets to be exactly CLOSED or CANCELLED
            $childTickets = Ticket::where('parent_ticket_id', $lockedTicket->id)
                ->lockForUpdate()
                ->get();

            if ($childTickets->isNotEmpty()) {
                $hasUnresolved = $childTickets->contains(function (Ticket $child) {
                    return ! in_array($child->status, [TicketStatus::CLOSED, TicketStatus::CANCELLED], true);
                });

                if ($hasUnresolved) {
                    $nonTerminal = $childTickets->reject(function (Ticket $child) {
                        return in_array($child->status, [TicketStatus::CLOSED, TicketStatus::CANCELLED], true);
                    })->map(fn (Ticket $child) => "{$child->ticket_number} ({$child->status->label()})")->implode(', ');

                    throw ValidationException::withMessages([
                        'child_tickets' => "Cannot force-close ticket while subordinate child tickets are active. All child tickets must be exactly closed or cancelled. Active children: {$nonTerminal}",
                    ]);
                }
            }

            $oldStatus = $lockedTicket->status;
            $assignedTeamId = $lockedTicket->team_id;

            // Close active field team assignment immediately
            $lockedTicket->assignments()
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            // Update ticket to CLOSED with force-closure audit attributes (preserving assigned team for audit & crew visibility)
            $lockedTicket->update([
                'status' => TicketStatus::CLOSED,
                'is_force_closed' => true,
                'closure_reason' => trim($data['closure_reason']),
                'closed_by_id' => $actor->id,
                'closed_at' => now(),
            ]);

            TicketStatusLog::create([
                'ticket_id' => $lockedTicket->id,
                'old_status' => $oldStatus->value,
                'new_status' => TicketStatus::CLOSED->value,
                'changed_by_id' => $actor->id,
            ]);

            DB::afterCommit(function () use ($lockedTicket, $assignedTeamId) {
                rescue(fn () => event(new TicketForceClosed($lockedTicket, $assignedTeamId)), report: true);
            });

            return $lockedTicket->fresh([
                'category',
                'department',
                'team',
                'closedBy.role',
                'statusLog.updater',
            ]);
        });
    }
}

