<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Api\Tickets\EndorsementRequest;
use Illuminate\Support\Facades\Gate;
use App\Models\Ticket;
use App\Services\Api\Tickets\TicketService;
use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Http\Resources\Api\EndorsementOptionsResource;
use App\Http\Resources\Api\TicketResource;

class TicketEndorsementController extends Controller
{
    public function __construct(protected TicketService $ticketService) {}

    public function endorsementOptions(Request $request, Ticket $ticket)
    {
        Gate::authorize('endorse', $ticket);

        $departments = $this->ticketService->getEndorsementOptions($request->user());

        return EndorsementOptionsResource::collection($departments);
    }

    public function endorse(EndorsementRequest $request, Ticket $ticket)
    {
        // 1. Security Gate
        Gate::authorize('endorse', $ticket);

        $allowedStatuses = [
            TicketStatus::OPEN,
            TicketStatus::ASSIGNED,
            TicketStatus::IN_PROGRESS
        ];

        if (!in_array($ticket->status, $allowedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Tickets that are resolved, closed, or locked in an endorsement workflow cannot be endorsed.'
            ], 422);
        }

        // 3. State Guard: Prevent duplicate pending requests
        if ($ticket->endorsements()->where('status', EndorsementStatus::PENDING->value)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket already has a pending endorsement request.'
            ], 422);
        }

        // 4. Delegate to Service
        $endorsement = $this->ticketService->requestEndorsement(
            $ticket, 
            $request->validated(), 
            $request->user()
        );

        return response()->json([
            'success' => true,
            'status'  => 201,
            'message' => "An endorsement request has been submitted. Ticket {$ticket->ticket_number} is now frozen pending CWD review.",
            'data'    => new TicketResource($endorsement)
        ]);
    }
}