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
        // 1. Security Gate: Checks user role privilege
        Gate::authorize('assign', $ticket);
        
        $user = $request->user();
        $requestedTeamId = $request->validated('team_id');

        // 2. Idempotency Gate: Prevent logging if the team is already assigned
        if ($ticket->team_id === $requestedTeamId) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is already assigned to the selected team. No changes were made.'
            ], 422); 
        }

        // 3. Action Detection: Determine if it's a fresh assignment or a reassignment
        $action = is_null($ticket->team_id) ? 'assigned' : 'reassigned';

        // 4. Execute transactional assignment
        $updatedTicket = $this->ticketService->assignTicket(
            $ticket, 
            $requestedTeamId, 
            $user
        );

        return response()->json([
            'success' => true,
            'status'  => 200,
            // 5. Inject the dynamic action into the success message
            'message' => "Ticket {$updatedTicket->ticket_number} has been successfully {$action}.",
            'data'    => new TicketResource($updatedTicket)
        ]);
    }
}