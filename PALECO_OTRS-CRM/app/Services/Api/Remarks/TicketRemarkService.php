<?php

namespace App\Services\Api\Remarks;

use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Models\User;
use Illuminate\Support\Collection;

class TicketRemarkService
{
    public function getTimeline(Ticket $ticket): Collection
    {
        return $ticket->remarks()
            ->with('author.role')
            ->where('is_internal', false) // Unconditionally hide for mobile
            ->get();
    }

    public function createRemark(Ticket $ticket, User $user, array $data): TicketRemark
    {
        return $ticket->remarks()->create([
            'user_id'     => $user->id,
            'body'        => $data['body'],
            'is_internal' => false, // Unconditionally force false for mobile
        ]);
    }
}