<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service encapsulating accomplishment report queries for tickets.
 */
class TicketAccomplishmentService
{
    // --- QUERY METHODS ---

    /**
     * Retrieves all accomplishment reports submitted for a ticket.
     */
    public function getAccomplishments(Ticket $ticket): Collection
    {
        return $ticket->accomplishments()
            ->with(['accomplishedBy', 'approvedBy', 'rejectedBy', 'photos'])
            ->latest('accomplished_at')
            ->get();
    }

    /**
     * Eagerly loads relationships for detailed accomplishment inspection.
     * Optionally validates that the accomplishment belongs to the provided ticket context.
     */
    public function getAccomplishmentDetails(TicketAccomplishment $accomplishment, ?Ticket $ticket = null): TicketAccomplishment
    {
        if ($ticket && $accomplishment->ticket_id !== $ticket->id) {
            abort(404, 'This accomplishment report does not belong to the requested ticket.');
        }

        return $accomplishment->load(['accomplishedBy', 'approvedBy', 'rejectedBy', 'photos']);
    }
}
