<?php

namespace App\Services\Tickets;

use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\CancelTicket;
use App\Actions\Tickets\ForceCloseTicket;
use App\Actions\Tickets\StartTicket;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Service encapsulating ticket lifecycle state transitions and mutations.
 * Delegates discrete state mutations to dedicated Action classes:
 * - AssignTicket
 * - StartTicket
 * - CancelTicket
 * - ForceCloseTicket
 */
class TicketLifecycleService
{
    public function __construct(
        protected AssignTicket $assignTicketAction,
        protected StartTicket $startTicketAction,
        protected CancelTicket $cancelTicketAction,
        protected ForceCloseTicket $forceCloseTicketAction
    ) {}

    /**
     * Assigns or reassigns a service ticket to a designated field team.
     *
     * @throws ValidationException
     */
    public function assignTicket(Ticket $ticket, string $teamId, User $assigner, ?string $reason = null): Ticket
    {
        return $this->assignTicketAction->execute($ticket, $teamId, $assigner, $reason);
    }

    /**
     * Transitions an assigned ticket into IN_PROGRESS status upon field work commencement.
     *
     * @throws ValidationException
     */
    public function startTicket(Ticket $ticket, User $worker, ?Carbon $clientTimestamp = null): Ticket
    {
        return $this->startTicketAction->execute($ticket, $worker, $clientTimestamp);
    }

    /**
     * Cancels an open service ticket upon dispatcher review.
     *
     * @throws ValidationException
     */
    public function cancelTicket(Ticket $ticket, array $data, User $actor): Ticket
    {
        return $this->cancelTicketAction->execute($ticket, $data, $actor);
    }

    /**
     * Administratively force-closes an active service ticket.
     *
     * @throws ValidationException
     */
    public function forceCloseTicket(Ticket $ticket, array $data, User $actor): Ticket
    {
        return $this->forceCloseTicketAction->execute($ticket, $data, $actor);
    }
}
