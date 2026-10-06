<?php

namespace App\Services\Api\Remarks;

use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/*
 * Encapsulates the retrieval and creation logic for ticket remarks within the mobile API.
 * Enforces business rules such as strictly hiding internal remarks from mobile visibility.
 */
class TicketRemarkService
{
    // --- QUERY METHODS ---

    /*
     * Retrieves public timeline remarks for a ticket with eager-loaded author details.
     */
    public function getTimeline(Ticket $ticket, ?User $user = null): Collection
    {
        return $ticket->remarks()
            ->with('author.role')
            ->where('is_internal', false)
            ->latest()
            ->get();
    }

    // --- MUTATING METHODS ---

    /*
     * Creates a new public remark attributed to the authenticated mobile user.
     */
    public function createRemark(Ticket $ticket, User $user, array $data): TicketRemark
    {
        return $ticket->remarks()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
            'is_internal' => false,
        ]);
    }
}
