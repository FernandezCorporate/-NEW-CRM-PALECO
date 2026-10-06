<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TicketDetailedResource;
use App\Http\Resources\Api\TicketHistoryResource;
use App\Http\Resources\Api\TicketResource;
use App\Models\Ticket;
use App\Services\Api\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/*
 * Manages ticket indexing, detail retrieval, work commencement, and timeline inspection for the mobile API.
 */
class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves paginated tickets scoped to the user's role along with global status counts.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewInbox', Ticket::class);

        $user = $request->user()->load('role');

        $tickets = $this->ticketService->getInboxTickets(
            $user,
            $request->only(['search', 'category', 'status', 'sort'])
        );

        $counts = $this->ticketService->getTicketStatusCount($user);

        return TicketResource::collection($tickets)->additional([
            'success' => true,
            'meta' => [
                'status_counts' => $counts,
            ],
        ]);
    }

    /*
     * Retrieves comprehensive details, relations, and child ticket counts for a specific ticket.
     */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $detailedTicket = $this->ticketService->getDetailedTicketMobile($ticket);

        return response()->json([
            'success' => true,
            'data' => new TicketDetailedResource($detailedTicket),
        ], Response::HTTP_OK);
    }

    /*
     * Fetches the complete assignment and endorsement history for the specified ticket.
     */
    public function history(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('viewHistory', $ticket);

        $result = $this->ticketService->viewTicketHistory($ticket);

        return response()->json([
            'success' => true,
            'message' => "Assignment and endorsement history for {$ticket->ticket_number} has been retrieved.",
            'data' => new TicketHistoryResource($result),
        ], Response::HTTP_OK);
    }

    // --- MUTATING METHODS ---

    /*
     * Transitions an assigned ticket to in-progress status upon field personnel departure or work start.
     */
    public function start(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('start', $ticket);

        $updatedTicket = $this->ticketService->startTicket($ticket, $request->user());

        return response()->json([
            'success' => true,
            'message' => "Work has started on ticket {$updatedTicket->ticket_number}.",
            'data' => new TicketResource($updatedTicket),
        ], Response::HTTP_OK);
    }
}
