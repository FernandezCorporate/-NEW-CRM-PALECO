<?php

namespace App\Actions\Accomplishments;

use App\Enums\TicketAccomplishmentStatus;
use App\Enums\TicketStatus;
use App\Events\TicketVerifiedAndClosed;
use App\Events\TicketWorkStarted;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Evaluates a submitted accomplishment report (approves and closes ticket or rejects back to in-progress).
 */
class VerifyAccomplishment
{
    /**
     * @throws ValidationException
     */
    public function handle(
        Ticket $ticket,
        TicketAccomplishment $accomplishment,
        array $data,
        User $supervisor
    ): TicketAccomplishment {
        return $this->execute($ticket, $accomplishment, $data, $supervisor);
    }

    /**
     * @throws ValidationException
     */
    public function execute(
        Ticket $ticket,
        TicketAccomplishment $accomplishment,
        array $data,
        User $supervisor
    ): TicketAccomplishment {
        if ($accomplishment->ticket_id !== $ticket->id) {
            abort(404, 'This accomplishment report does not belong to the requested ticket.');
        }

        return DB::transaction(function () use ($ticket, $accomplishment, $data, $supervisor) {
            $lockedAccomplishment = TicketAccomplishment::where('id', $accomplishment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAccomplishment->status !== TicketAccomplishmentStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'This accomplishment report has already been evaluated.',
                ]);
            }

            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Only resolved tickets can be evaluated; prevents re-opening or re-closing force-closed tickets
            if ($lockedTicket->status !== TicketStatus::RESOLVED) {
                throw ValidationException::withMessages([
                    'status' => 'This accomplishment report can no longer be evaluated because the ticket is '.$lockedTicket->status->label().'.',
                ]);
            }

            $oldTicketStatus = $lockedTicket->status;

            if ($data['status'] === TicketAccomplishmentStatus::APPROVED->value) {
                $lockedAccomplishment->update([
                    'status' => TicketAccomplishmentStatus::APPROVED,
                    'approved_by_id' => $supervisor->id,
                ]);

                // Close active assignment since the ticket's field lifecycle is completely finalized
                $lockedTicket->assignments()
                    ->whereNull('unassigned_at')
                    ->update(['unassigned_at' => now()]);

                $lockedTicket->update([
                    'status' => TicketStatus::CLOSED,
                    'closed_at' => now(),
                ]);

                TicketStatusLog::create([
                    'ticket_id' => $lockedTicket->id,
                    'old_status' => $oldTicketStatus,
                    'new_status' => TicketStatus::CLOSED,
                    'changed_by_id' => $supervisor->id,
                ]);

                $freshTicket = $lockedTicket->fresh(['category', 'team', 'department']);

                DB::afterCommit(function () use ($freshTicket) {
                    rescue(fn () => TicketVerifiedAndClosed::dispatch($freshTicket), report: true);
                });
            }

            if ($data['status'] === TicketAccomplishmentStatus::REJECTED->value) {
                $lockedAccomplishment->update([
                    'status' => TicketAccomplishmentStatus::REJECTED,
                    'rejection_reason' => $data['rejection_reason'],
                    'rejected_by_id' => $supervisor->id,
                ]);

                $lockedTicket->update([
                    'status' => TicketStatus::IN_PROGRESS,
                    'resolved_at' => null,
                ]);

                TicketStatusLog::create([
                    'ticket_id' => $lockedTicket->id,
                    'old_status' => $oldTicketStatus,
                    'new_status' => TicketStatus::IN_PROGRESS,
                    'changed_by_id' => $supervisor->id,
                ]);

                $freshTicket = $lockedTicket->fresh(['category', 'team', 'department']);

                DB::afterCommit(function () use ($freshTicket) {
                    rescue(fn () => TicketWorkStarted::dispatch($freshTicket), report: true);
                });
            }

            return $lockedAccomplishment->fresh(['accomplishedBy', 'rejectedBy', 'approvedBy', 'photos']);
        });
    }
}

