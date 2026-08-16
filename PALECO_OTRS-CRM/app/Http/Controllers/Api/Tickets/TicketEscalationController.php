<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Api\Tickets\EscalationRequest;
use Illuminate\Support\Facades\Gate;
use App\Models\Ticket;
use App\Services\Api\Tickets\TicketService;
use App\Enums\EscalationStatus;
use App\Http\Resources\Api\EscalateOptionsResource;
use App\Http\Resources\Api\TicketResource;

class TicketEscalationController extends Controller
{
    public function __construct(protected TicketService $ticketService) {}

    public function escalateOptions(Request $request, Ticket $ticket)
    {
        Gate::authorize('escalate', $ticket);

        $departments = $this->ticketService->getEscalationOptions($request->user());

        return EscalateOptionsResource::collection($departments);
    }

    public function escalate(EscalationRequest $request, Ticket $ticket)
    {
        // 1. Security Gate
        Gate::authorize('escalate', $ticket);

        // 2. Integrity Guard: Prevent escalating to the same department
        if ((int) $request->suggested_department_id === $ticket->department_id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot escalate a ticket to your own department.'
            ], 422);
        }

        // 3. State Guard: Prevent duplicate pending requests
        if ($ticket->escalations()->where('status', EscalationStatus::PENDING->value)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket already has a pending escalation request.'
            ], 422);
        }

        // 4. Delegate to Service
        $escalation = $this->ticketService->requestEscalation(
            $ticket, 
            $request->validated(), 
            $request->user()
        );

        return response()->json([
            'success' => true,
            'status'  => 201,
            'message' => "Escalation request submitted. Ticket {$ticket->ticket_number} is now frozen pending CWD review.",
            'data'    => new TicketResource($escalation)
        ]);
    }
}
