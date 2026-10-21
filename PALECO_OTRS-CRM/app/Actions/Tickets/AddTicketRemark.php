<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Stores a new communication remark against a specific ticket.
 */
class AddTicketRemark
{
    public function handle(Ticket $ticket, User $user, array $data): TicketRemark
    {
        return $this->execute($ticket, $user, $data);
    }

    public function execute(Ticket $ticket, User $user, array $data): TicketRemark
    {
        return DB::transaction(function () use ($ticket, $user, $data) {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->firstOrFail();

            // Re-checked under the lock: the ticket may have closed after the page or app loaded it
            if (in_array($lockedTicket->status, [TicketStatus::CLOSED, TicketStatus::CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Closed and cancelled tickets are read-only. Remarks can no longer be added.',
                ]);
            }

            return $lockedTicket->remarks()->create([
                'user_id' => $user->id,
                'body' => $data['body'],
                'is_internal' => (bool) ($data['is_internal'] ?? false),
            ]);
        });
    }
}

