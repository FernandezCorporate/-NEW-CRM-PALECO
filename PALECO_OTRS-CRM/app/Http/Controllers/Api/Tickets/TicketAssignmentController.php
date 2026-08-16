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

    public function assignOptions(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('assign', $ticket);

        $teams = $this->ticketService->getAssignOptions($request->user(), $ticket);

        return AssignOptionResource::collection($teams);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('assign', $ticket);
        
        $allowedStatuses = [
            TicketStatus::OPEN, 
            TicketStatus::ASSIGNED, 
            TicketStatus::IN_PROGRESS
        ];

        if (!in_array($ticket->status, $allowedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Tickets that are resolved, closed, or locked in an escalation workflow cannot be reassigned.'
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