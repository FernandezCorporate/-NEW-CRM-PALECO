<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Events\TicketCancelled;
use App\Models\Ticket;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Cancels an open service ticket upon dispatcher review.
 */
class CancelTicket
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
                    'actor' => 'Only CWD officers are authorized to cancel tickets.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'This ticket is already cancelled.',
                ]);
            }

            if ($lockedTicket->status !== TicketStatus::OPEN) {
                throw ValidationException::withMessages([
                    'status' => 'Ticket can only be cancelled while in Open status. Once assigned to a team or dispatched, cancellation is locked.',
                ]);
            }

            // Children may belong to other departments, so they are never cancelled automatically
            $activeChildren = Ticket::where('parent_ticket_id', $lockedTicket->id)
                ->where('status', '!=', TicketStatus::CANCELLED)
                ->orderBy('ticket_number')
                ->get();

            if ($activeChildren->isNotEmpty()) {
                $list = $activeChildren->map(fn (Ticket $child) => "{$child->ticket_number} ({$child->status->label()})")->implode(', ');

                throw ValidationException::withMessages([
                    'child_tickets' => "Cannot cancel this ticket while it has child tickets that are not cancelled: {$list}. Cancel them first.",
                ]);
            }

            $oldStatus = $lockedTicket->status;

            TicketStatusLog::create([
                'ticket_id' => $lockedTicket->id,
                'old_status' => $oldStatus,
                'new_status' => TicketStatus::CANCELLED,
                'changed_by_id' => $actor->id,
            ]);

            $lockedTicket->update([
                'status' => TicketStatus::CANCELLED,
                'cancellation_reason' => trim($data['cancellation_reason']),
                'cancelled_at' => now(),
            ]);

            DB::afterCommit(function () use ($lockedTicket) {
                rescue(fn () => event(new TicketCancelled($lockedTicket)), report: true);
            });

            return $lockedTicket->fresh([
                'category',
                'department',
                'team',
                'statusLog.updater',
            ]);
        });
    }
}
