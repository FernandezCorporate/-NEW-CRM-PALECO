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
use App\Http\Requests\Api\Tickets\SubmitAccomplishmentReport;

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

    /*
     * Updates the ticket status to in-progress and sets the start timestamp.
     * Ensures only correctly assigned tickets can be initiated.
     */
    public function start(Request $request, Ticket $ticket): JsonResponse
    {
        // 1. Security Gate: Ensure user is on the assigned team
        Gate::authorize('start', $ticket);

        // 2. State Guard: Prevent starting a ticket that isn't in the ASSIGNED state
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

        // 3. Execute transactional update
        $updatedTicket = $this->ticketService->startTicket($ticket, $request->user());

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => "Work has started on ticket {$updatedTicket->ticket_number}.",
            'data'    => new TicketResource($updatedTicket)
        ]);
    }

    public function accomplish(SubmitAccomplishmentReport $request, Ticket $ticket)
    {
        // 1. Security Gate
        Gate::authorize('accomplish', $ticket);

        // 2. State Guard
        if ($ticket->status === TicketStatus::RESOLVED) {
            return response()->json([
                'success' => false,
                'message' => 'An accomplishment report has already been submitted for this ticket.'
            ], 422);
        }


        if ($ticket->status !== TicketStatus::IN_PROGRESS) {
            return response()->json([
                'success' => false,
                'message' => 'Only tickets that are in progress can be marked as accomplished.'
            ], 422);
        }

        // 3. Execute transactional update (Service now returns the report)
        $accomplishmentReport = $this->ticketService->accomplishTicket($ticket, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'status'  => 201,
            // 4. Use $ticket->ticket_number instead of the report's property
            'message' => "Accomplishment report for ticket {$ticket->ticket_number} has been submitted.",
            'data'    => $accomplishmentReport->load('accomplishedBy') // This will now work perfectly
        ]);
    }
}