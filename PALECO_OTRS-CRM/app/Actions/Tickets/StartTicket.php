<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Events\TicketWorkStarted;
use App\Models\Ticket;
use App\Models\TicketStatusLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Transitions an assigned ticket into IN_PROGRESS status upon field work commencement.
 */
class StartTicket
{
    /**
     * @throws ValidationException
     */
    public function handle(Ticket $ticket, User $worker, ?Carbon $clientTimestamp = null): Ticket
    {
        return $this->execute($ticket, $worker, $clientTimestamp);
    }

    /**
     * @throws ValidationException
     */
    public function execute(Ticket $ticket, User $worker, ?Carbon $clientTimestamp = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $worker, $clientTimestamp) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency safety: If already in progress, return ticket safely without throwing error
            if ($lockedTicket->status === TicketStatus::IN_PROGRESS) {
                return $lockedTicket->fresh(['category']);
            }

            if ($lockedTicket->status === TicketStatus::PENDING_ENDORSEMENT) {
                throw ValidationException::withMessages([
                    'status' => 'Ticket is locked pending inter-departmental endorsement review.',
                ]);
            }

            if ($lockedTicket->status !== TicketStatus::ASSIGNED) {
                throw ValidationException::withMessages([
                    'status' => 'Only assigned tickets can be started.',
                ]);
            }

            $isOffline = $clientTimestamp !== null && $clientTimestamp->diffInMinutes(now()) > 5;
            $actualStartTime = $clientTimestamp ?? now();

            $oldStatus = $lockedTicket->status;

            TicketStatusLog::create([
                'ticket_id' => $lockedTicket->id,
                'old_status' => $oldStatus,
                'new_status' => TicketStatus::IN_PROGRESS,
                'changed_by_id' => $worker->id,
                'created_at' => $actualStartTime,
            ]);

            $lockedTicket->update([
                'status' => TicketStatus::IN_PROGRESS,
                'started_at' => $actualStartTime,
                'client_started_at' => $clientTimestamp,
                'is_offline_synced' => $isOffline,
                'synced_at' => $isOffline ? now() : null,
            ]);

            $freshTicket = $lockedTicket->fresh(['category', 'team', 'department']);

            DB::afterCommit(function () use ($freshTicket) {
                rescue(fn () => TicketWorkStarted::dispatch($freshTicket), report: true);
            });

            return $freshTicket;
        });
    }
}
