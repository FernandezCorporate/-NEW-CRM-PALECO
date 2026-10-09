<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\AssignTicketRequest;
use App\Http\Resources\Api\AssignOptionResource;
use App\Http\Resources\Api\TicketResource;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/*
 * Manages ticket assignment options retrieval and team dispatch assignments for supervisors.
 */
class TicketAssignmentController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves assignable teams within the supervisor's department with current active workloads.
     */
    public function assignOptions(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('assign', $ticket);

        $teams = $this->ticketService->getAssignOptions($request->user(), $ticket);

        return response()->json([
            'success' => true,
            'data' => AssignOptionResource::collection($teams),
        ], Response::HTTP_OK);
    }

    // --- MUTATING METHODS ---

    /*
     * Assigns or reassigns a service ticket to a designated team with an optional audit reason.
     */
    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('assign', $ticket);

        $action = is_null($ticket->team_id) ? 'assigned' : 'reassigned';

        $updatedTicket = $this->ticketService->assignTicket(
            $ticket,
            $request->validated('team_id'),
            $request->user(),
            $request->validated('reason')
        );

        return response()->json([
            'success' => true,
            'message' => "Ticket {$updatedTicket->ticket_number} has been successfully {$action}.",
            'data' => new TicketResource($updatedTicket),
        ], Response::HTTP_OK);
    }
}
