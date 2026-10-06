<?php

namespace App\Services\Web\Remarks;

use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Models\User;

/*
 * Manages the creation and association of chronological communication remarks for tickets in the web app.
 */
class TicketRemarkService
{
    // --- MUTATING METHODS ---

    /*
     * Stores a new communication remark against a specific ticket with visibility flags.
     */
    public function createRemark(Ticket $ticket, User $user, array $data): TicketRemark
    {
        return $ticket->remarks()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
            'is_internal' => $data['is_internal'] ?? false,
        ]);
    }
}
