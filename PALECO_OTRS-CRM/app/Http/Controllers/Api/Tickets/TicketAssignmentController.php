<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tickets\AssignTicketRequest;
use App\Http\Resources\Api\AssignOptionResource;
use App\Http\Resources\Api\TicketResource;
use App\Models\Ticket;
use App\Services\Api\Tickets\TicketService;
use App\Enums\TicketStatus; // <-- IMPORT ADDED
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketAssignmentController extends Controller
{
    public function __construct(protected TicketService $ticketService) {}

    public function assignOptions(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('assignOptions', Ticket::class);

        $teams = $this->ticketService->getAssignOptions($request->user());

        return AssignOptionResource::collection($teams);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('assign', $ticket);
        
        // --- NEW GUARD: Prevent assignment if ticket is escalated ---
        if (in_array($ticket->status, [TicketStatus::PENDING_ESCALATION, TicketStatus::ESCALATED])) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is currently locked in an escalation workflow and cannot be reassigned.'
            ], 422);
        }
        // ------------------------------------------------------------
        
        $user = $request->user();
        $requestedTeamId = $request->validated('team_id');

        if ($ticket->team_id === $requestedTeamId) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is already assigned to the selected team. No changes were made.'
            ], 422); 
        }

        $action = is_null($ticket->team_id) ? 'assigned' : 'reassigned';

        $updatedTicket = $this->ticketService->assignTicket(
            $ticket, 
            $requestedTeamId, 
            $user
        );

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => "Ticket {$updatedTicket->ticket_number} has been successfully {$action}.",
            'data'    => new TicketResource($updatedTicket)
        ]);
    }
}