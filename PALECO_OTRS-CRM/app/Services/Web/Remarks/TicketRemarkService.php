<?php

namespace App\Services\Web\Remarks;

use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Models\User;

class TicketRemarkService
{
    /*
     * Stores a new communication remark against a specific ticket.
     */
    public function createRemark(Ticket $ticket, User $user, array $data): TicketRemark
    {
        return $ticket->remarks()->create([
            'user_id'     => $user->id,
            'body'        => $data['body'],
            'is_internal' => $data['is_internal'] ?? false,
        ]);
    }
}