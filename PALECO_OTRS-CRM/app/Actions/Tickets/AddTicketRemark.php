<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Models\User;

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
        return $ticket->remarks()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
            'is_internal' => (bool) ($data['is_internal'] ?? false),
        ]);
    }
}

