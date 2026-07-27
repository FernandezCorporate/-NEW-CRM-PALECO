<?php

namespace App\Services\Api\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/*
 * Encapsulates the core ticket retrieval logic for the mobile API.
 * Enforces department-level scoping and strict data formatting constraints.
 */
class TicketService
{
    // --- CORE PROCESSES ---

    /*
     * Retrieves a paginated, mapped list of tickets securely scoped to the user's assigned department.
     */
    public function getDepartmentTickets(User $user, array $params): LengthAwarePaginator
    {
        // 1. Base Query: Restrict strictly to the authenticated user's department and load necessary relations
        $query = Ticket::query()
            ->where('department_id', $user->department_id)
            ->with('category')
            ->withCount('childTickets');

        // 2. Apply API-specific Scopes to protect original web functionality
        $query->apiSearch($params['search'] ?? null)
              ->apiFilterByCategoryName($params['category'] ?? null)
              ->apiFilterByStatus($params['status'] ?? null)
              ->apiSort($params['sort'] ?? null);

        // 3. Execute query and enforce strict pagination limit of 10
        $paginatedTickets = $query->paginate(10);

        // 4. Transform the resulting collection to limit payload size and utilize null-safe extraction
        $paginatedTickets->getCollection()->transform(function ($ticket) {
            return [
                'ticket_number'       => $ticket->ticket_number,
                'complaint_source'    => $ticket->complaint_source?->value ?? $ticket->complaint_source,
                'category_name'       => $ticket->other_category 
                                            ? $ticket->other_category_name 
                                            : $ticket->category?->category_name,
                'purok'               => $ticket->purok,
                'street'              => $ticket->street,
                'barangay'            => $ticket->barangay,
                'status'              => $ticket->status?->value ?? $ticket->status,
                'child_tickets_count' => $ticket->child_tickets_count ?? 0,
            ];
        });

        return $paginatedTickets;
    }
}