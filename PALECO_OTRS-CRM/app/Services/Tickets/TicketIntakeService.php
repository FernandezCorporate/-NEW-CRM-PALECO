<?php

namespace App\Services\Tickets;

use App\Actions\Tickets\CreateCwdTicket;
use App\Actions\Tickets\CreateManualChildTicket;
use App\Actions\Tickets\UpdateTicketIntake;
use App\Models\Ticket;
use App\Services\External\PalecoBillingApiService;

/**
 * Service encapsulating ticket intake operations.
 * Delegates discrete state mutations to dedicated Action classes:
 * - CreateCwdTicket
 * - CreateManualChildTicket
 * - UpdateTicketIntake
 */
class TicketIntakeService
{
    public function __construct(
        protected PalecoBillingApiService $billingApiService,
        protected TicketSequenceService $sequenceService,
        protected CreateCwdTicket $createCwdTicketAction,
        protected CreateManualChildTicket $createChildTicketAction,
        protected UpdateTicketIntake $updateTicketIntakeAction
    ) {}

    /**
     * Persist a new consumer ticket along with initial status log and safe deferred event dispatch.
     */
    public function createCwdTicket(array $validatedData): Ticket
    {
        return $this->createCwdTicketAction->execute($validatedData);
    }

    /**
     * Manually spawn a child ticket linked directly to the parent ticket.
     */
    public function createManualChildTicket(Ticket $parentTicket, array $validatedData): Ticket
    {
        return $this->createChildTicketAction->execute($parentTicket, $validatedData);
    }

    /**
     * Updates clerical intake details (contact, location, description, category, consumer link) for an Open ticket.
     *
     * @return array{success: bool, changed: bool, ticket: Ticket}
     */
    public function updateIntakeDetails(Ticket $ticket, array $validatedData): array
    {
        return $this->updateTicketIntakeAction->execute($ticket, $validatedData);
    }
}
