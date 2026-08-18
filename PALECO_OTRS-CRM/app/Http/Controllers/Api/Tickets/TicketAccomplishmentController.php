<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tickets\SubmitAccomplishmentReport;
use App\Http\Requests\Api\Tickets\VerifyAccomplishmentRequest;
use App\Http\Resources\Api\TicketAccomplishmentResource;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Services\Api\Tickets\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Enums\TicketStatus;
use App\Enums\TicketAccomplishmentStatus;

class TicketAccomplishmentController extends Controller
{
    public function __construct(protected TicketService $ticketService) {}

    public function index(Request $request, Ticket $ticket)
    {
        Gate::authorize('view', $ticket);

        $accomplishments = $this->ticketService->getAccomplishments($ticket);

        return response()->json([
            'success' => true,
            'data' => TicketAccomplishmentResource::collection($accomplishments)
        ]);
    }

    public function show(Request $request, Ticket $ticket, TicketAccomplishment $accomplishment)
    {
        Gate::authorize('view', $ticket);

        if ($accomplishment->ticket_id !== $ticket->system_id) {
            return response()->json([
                'success' => false,
                'message' => 'Accomplishment report not found.'
            ], 404);
        }

        $loadedAccomplishment = $this->ticketService->getAccomplishmentDetails($accomplishment);

        return response()->json([
            'success' => true,
            'data' => new TicketAccomplishmentResource($loadedAccomplishment)
        ]);
    }

    public function store(SubmitAccomplishmentReport $request, Ticket $ticket)
    {
        Gate::authorize('accomplish', $ticket);

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

        $accomplishmentReport = $this->ticketService->accomplishTicket($ticket, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'status'  => 201,
            'message' => "Accomplishment report for ticket {$ticket->ticket_number} has been submitted.",
            'data'    => new TicketAccomplishmentResource($accomplishmentReport->load('accomplishedBy')) 
        ]);
    }

    public function verify(VerifyAccomplishmentRequest $request, Ticket $ticket, TicketAccomplishment $accomplishment)
    {
        // 1. Security Gate
        Gate::authorize('verify', $ticket);

        // 2. Data Integrity Guard
        if ($accomplishment->ticket_id !== $ticket->system_id) {
            return response()->json([
                'success' => false,
                'message' => 'This accomplishment report does not belong to the requested ticket.'
            ], 404);
        }

        // 3. State Guard
        if ($accomplishment->status !== TicketAccomplishmentStatus::PENDING) { 
            return response()->json([
                'success' => false,
                'message' => 'This accomplishment report has already been evaluated.'
            ], 422);
        }

        // 4. Delegate to Service
        $this->ticketService->verifyAccomplishment($ticket, $accomplishment, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => "Accomplishment report successfully {$request->status}."
        ]);
    }
}