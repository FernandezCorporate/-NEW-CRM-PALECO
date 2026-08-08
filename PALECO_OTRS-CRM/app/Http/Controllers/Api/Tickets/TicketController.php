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
use App\Enums\TicketStatus;

class TicketController extends Controller
{
    public function __construct(protected TicketService $ticketService) {}

    public function index(Request $request)
    {
        Gate::authorize('viewInbox', Ticket::class);

        $user = $request->user()->load('role');

        $tickets = $this->ticketService->getInboxTickets(
            $user, 
            $request->only(['search', 'category', 'status', 'sort'])
        );

        $counts = $this->ticketService->getTicketStatusCount($user);

        return TicketResource::collection($tickets)->additional([
            'meta' => [
                'status_counts' => $counts
            ]
        ]);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('assign', $ticket);
        
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

    public function start(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('start', $ticket);

        if ($ticket->status === TicketStatus::IN_PROGRESS) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is already in progress.'
            ], 422);
        }

        if ($ticket->status !== TicketStatus::ASSIGNED) {
            return response()->json([
                'success' => false,
                'message' => 'Only assigned tickets can be started.'
            ], 422);
        }

        $updatedTicket = $this->ticketService->startTicket($ticket, $request->user());

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => "Work has started on ticket {$updatedTicket->ticket_number}.",
            'data'    => new TicketResource($updatedTicket)
        ]);
    }
}