<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TicketResource;
use App\Http\Resources\Api\TicketDetailedResource;
use App\Models\Ticket;
use App\Services\Api\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Enums\TicketStatus;
use App\Http\Resources\Api\TicketHistoryResource;

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

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        // 1. Policy Gate (Automatically checks if Foreman owns department or Field owns team)
        Gate::authorize('view', $ticket);

        // 2. Fetch eager-loaded ticket via Service
        $detailedTicket = $this->ticketService->getDetailedTicketMobile($ticket);

        // 3. Return Fat Payload
        return response()->json([
            'success' => true,
            'data'    => new TicketDetailedResource($detailedTicket)
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

    public function history(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('viewHistory', $ticket);

        $result = $this->ticketService->viewTicketHistory($ticket);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => "Assignment and escalation history for {$ticket->ticket_numberp} has been retrieved.",
            'data'    => new TicketHistoryResource($result)
        ]);
    }
}