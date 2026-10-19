<?php

namespace App\Services\Tickets;

use App\Actions\Tickets\AddTicketRemark;
use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service encapsulating communication remarks and chronological timeline entries for tickets.
 * Handles internal/external visibility filtering across Web and Mobile portals.
 * Delegates remark creation to AddTicketRemark Action.
 */
class TicketRemarkService
{
    public function __construct(
        protected AddTicketRemark $addTicketRemarkAction
    ) {}

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
        return $this->addTicketRemarkAction->execute($ticket, $user, $data);
    }
}
