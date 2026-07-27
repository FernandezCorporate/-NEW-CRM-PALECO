<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tickets\AssignTicketRequest;
use App\Http\Resources\Api\TicketResource;
use App\Models\Ticket;
use App\Services\Api\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use App\Policies\TicketPolicy;

/*
 * Manages the service ticket inbox for mobile application users.
 * Handles querying the ticket registry restricted to the authenticated user's role.
 */
class TicketController extends Controller
{
    public function __construct(protected TicketService $ticketService) {}

    // --- VIEW METHODS ---

    public function index(Request $request)
    {
        Gate::authorize('viewInbox', Ticket::class);

        $user = $request->user()->load('role');

        $tickets = $this->ticketService->getInboxTickets(
            $user, 
            $request->only(['search', 'category', 'status', 'sort'])
        );

        return TicketResource::collection($tickets);
    }

    // --- MUTATING METHODS ---

    /*
     * Dispatches a ticket to a specific field team.
     * Rejects cross-department assignments and redundant same-team assignments.
     */
    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('assign', $ticket);
        $user = $request->user();
        $requestedTeamId = $request->validated('team_id');

        // 1. Security Gate: Ensure the Foreman owns this ticket's department
        if ($ticket->department_id !== $user->department_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access Denied: You cannot assign tickets belonging to another department.'
            ], 403); // 403 Forbidden
        }

        // 2. Idempotency Gate: Prevent logging if the team is already assigned
        if ($ticket->team_id === $requestedTeamId) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is already assigned to the selected team. No changes were made.'
            ], 422); // 422 Unprocessable Entity
        }

        // 3. Execute transactional assignment
        $updatedTicket = $this->ticketService->assignTicket(
            $ticket, 
            $requestedTeamId, 
            $user
        );

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => "Ticket {$updatedTicket->ticket_number} has been successfully dispatched.",
            'data'    => new TicketResource($updatedTicket)
        ]);
    }
}