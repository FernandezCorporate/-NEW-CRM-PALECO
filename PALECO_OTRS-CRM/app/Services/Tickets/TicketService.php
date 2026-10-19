<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Orchestrator facade for the ticket domain.
 * Delegates specialized read and query workflows to TicketQueryService.
 */
class TicketService
{
    public function __construct(
        protected TicketQueryService $queryService
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
}
