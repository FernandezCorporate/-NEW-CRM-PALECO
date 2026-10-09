<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreEndorsementRequest;
use App\Http\Resources\Api\EndorsementOptionResource;
use App\Http\Resources\Api\TicketEndorsementResource;
use App\Models\Ticket;
use App\Services\Tickets\TicketEndorsementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/*
 * Manages ticket endorsement department candidate querying and endorsement request submissions.
 */
class TicketEndorsementController extends Controller
{
    public function __construct(
        protected TicketEndorsementService $ticketEndorsementService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves the list of available departments for routing an endorsement request.
     */
    public function endorsementOptions(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('endorse', $ticket);

        $departments = $this->ticketEndorsementService->getEndorsementOptions($request->user());

        return response()->json([
            'success' => true,
            'data' => EndorsementOptionResource::collection($departments),
        ], Response::HTTP_OK);
    }

    // --- MUTATING METHODS ---

    /*
     * Submits a formal endorsement request, freezing the ticket into PENDING_ENDORSEMENT status.
     */
    public function endorse(StoreEndorsementRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('endorse', $ticket);

        $endorsement = $this->ticketEndorsementService->requestEndorsement(
            $ticket,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => "An endorsement request has been submitted. Ticket {$ticket->ticket_number} is now frozen pending CWD review.",
            'data' => new TicketEndorsementResource($endorsement->load(['suggestedDepartment', 'creator.role'])),
        ], Response::HTTP_CREATED);
    }
}
