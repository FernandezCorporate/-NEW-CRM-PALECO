<?php

namespace App\Services\Tickets;

use App\Actions\Accomplishments\SubmitAccomplishment;
use App\Actions\Accomplishments\VerifyAccomplishment;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Service encapsulating accomplishment report queries and lifecycle workflows.
 * Delegates discrete state mutations to dedicated Action classes:
 * - SubmitAccomplishment
 * - VerifyAccomplishment
 */
class TicketAccomplishmentService
{
    public function __construct(
        protected SubmitAccomplishment $submitAccomplishmentAction,
        protected VerifyAccomplishment $verifyAccomplishmentAction
    ) {}

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

    // --- MUTATING METHODS ---

    /**
     * Submits a new accomplishment report with signature and photos, transitioning ticket to RESOLVED.
     *
     * @throws ValidationException
     */
    public function accomplishTicket(
        Ticket $ticket,
        User $worker,
        array $data,
        ?Carbon $clientTimestamp = null,
        ?string $idempotencyKey = null
    ): TicketAccomplishment {
        return $this->submitAccomplishmentAction->execute($ticket, $worker, $data, $clientTimestamp, $idempotencyKey);
    }

    /**
     * Evaluates a submitted accomplishment report (approves and closes ticket or rejects back to in-progress).
     *
     * @throws ValidationException
     */
    public function verifyAccomplishment(Ticket $ticket, TicketAccomplishment $accomplishment, array $data, User $supervisor): TicketAccomplishment
    {
        return $this->verifyAccomplishmentAction->execute($ticket, $accomplishment, $data, $supervisor);
    }
}
