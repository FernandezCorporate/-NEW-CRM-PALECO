<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service encapsulating communication remarks and chronological timeline queries for tickets.
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
}
