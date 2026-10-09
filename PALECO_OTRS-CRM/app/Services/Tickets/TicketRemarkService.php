<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Encapsulates communication remarks and chronological timeline entries for tickets.
 * Handles internal/external visibility filtering across Web and Mobile portals.
 */
class TicketRemarkService
{
    // --- QUERY METHODS ---

    /**
     * Retrieves public timeline remarks for a ticket with eager-loaded author details.
     */
    public function getTimeline(Ticket $ticket, bool $includeInternal = false): Collection
    {
        $query = $ticket->remarks()
            ->with('author.role')
            ->latest();

        if (! $includeInternal) {
            $query->where('is_internal', false);
        }

        return $query->get();
    }

    // --- MUTATING METHODS ---

    /**
     * Stores a new communication remark against a specific ticket.
     */
    public function createRemark(Ticket $ticket, User $user, array $data): TicketRemark
    {
        return $ticket->remarks()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
            'is_internal' => (bool) ($data['is_internal'] ?? false),
        ]);
    }
}
