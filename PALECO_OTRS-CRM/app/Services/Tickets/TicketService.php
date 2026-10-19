<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Orchestrator facade for the ticket domain.
 * Delegates specialized workflows to TicketQueryService, TicketIntakeService,
 * TicketLifecycleService, and TicketSequenceService.
 */
class TicketService
{
    public function __construct(
        protected TicketQueryService $queryService,
        protected TicketIntakeService $intakeService,
        protected TicketLifecycleService $lifecycleService,
        protected TicketSequenceService $sequenceService
    ) {}

    // --- QUERY ORCHESTRATION ---

    public function getTicketList(Request $request): array
    {
        return $this->queryService->getTicketList($request);
    }

    public function getTicketDetails(Ticket $ticket): array
    {
        return $this->queryService->getTicketDetails($ticket);
    }

    public function loadTicketForm(): array
    {
        return $this->queryService->loadTicketForm();
    }

    public function loadChildTicketForm(Ticket $parentTicket): array
    {
        return $this->queryService->loadChildTicketForm($parentTicket);
    }

    public function loadTicketEditForm(Ticket $ticket): array
    {
        return $this->queryService->loadTicketEditForm($ticket);
    }

    public function getInboxTickets(User $user, array $params): LengthAwarePaginator
    {
        return $this->queryService->getInboxTickets($user, $params);
    }

    public function getTicketStatusCount(User $user): array
    {
        return $this->queryService->getTicketStatusCount($user);
    }

    public function getDetailedTicketMobile(Ticket $ticket): Ticket
    {
        return $this->queryService->getDetailedTicketMobile($ticket);
    }

    public function viewTicketHistory(Ticket $ticket): Ticket
    {
        return $this->queryService->viewTicketHistory($ticket);
    }

    public function getAssignOptions(User $supervisor, Ticket $ticket): Collection
    {
        return $this->queryService->getAssignOptions($supervisor, $ticket);
    }

    // --- INTAKE ORCHESTRATION ---

    public function createCwdTicket(array $validatedData): Ticket
    {
        return $this->intakeService->createCwdTicket($validatedData);
    }

    public function createManualChildTicket(Ticket $parentTicket, array $validatedData): Ticket
    {
        return $this->intakeService->createManualChildTicket($parentTicket, $validatedData);
    }

    public function updateIntakeDetails(Ticket $ticket, array $validatedData): array
    {
        return $this->intakeService->updateIntakeDetails($ticket, $validatedData);
    }

    // --- LIFECYCLE & STATE MUTATION ORCHESTRATION ---

    public function assignTicket(Ticket $ticket, string $teamId, User $assigner, ?string $reason = null): Ticket
    {
        return $this->lifecycleService->assignTicket($ticket, $teamId, $assigner, $reason);
    }

    public function startTicket(Ticket $ticket, User $worker, ?Carbon $clientTimestamp = null): Ticket
    {
        return $this->lifecycleService->startTicket($ticket, $worker, $clientTimestamp);
    }

    public function cancelTicket(Ticket $ticket, array $data, User $actor): Ticket
    {
        return $this->lifecycleService->cancelTicket($ticket, $data, $actor);
    }

    public function forceCloseTicket(Ticket $ticket, array $data, User $actor): Ticket
    {
        return $this->lifecycleService->forceCloseTicket($ticket, $data, $actor);
    }

    // --- SEQUENCE NUMBER ORCHESTRATION ---

    public function generateSequentialNumber(): string
    {
        return $this->sequenceService->generateSequentialNumber();
    }

    public function generateChildTicketNumber(Ticket $parentTicket): string
    {
        return $this->sequenceService->generateChildTicketNumber($parentTicket);
    }
}
