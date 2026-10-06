<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tickets\SubmitAccomplishmentReportRequest;
use App\Http\Requests\Api\Tickets\VerifyAccomplishmentRequest;
use App\Http\Resources\Api\TicketAccomplishmentResource;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Services\Api\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/*
 * Manages the retrieval, submission, and supervisory verification of ticket accomplishment reports.
 */
class TicketAccomplishmentController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves all accomplishment reports submitted for a ticket.
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $accomplishments = $this->ticketService->getAccomplishments($ticket);

        return response()->json([
            'success' => true,
            'data' => TicketAccomplishmentResource::collection($accomplishments),
        ], Response::HTTP_OK);
    }

    /*
     * Fetches detailed accomplishment data including photos and verification logs.
     */
    public function show(Request $request, Ticket $ticket, TicketAccomplishment $accomplishment): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $loadedAccomplishment = $this->ticketService->getAccomplishmentDetails($ticket, $accomplishment);

        return response()->json([
            'success' => true,
            'data' => new TicketAccomplishmentResource($loadedAccomplishment),
        ], Response::HTTP_OK);
    }

    // --- MUTATING METHODS ---

    /*
     * Submits a new accomplishment report with photo evidence, moving the ticket to RESOLVED.
     */
    public function store(SubmitAccomplishmentReportRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('accomplish', $ticket);

        $accomplishmentReport = $this->ticketService->accomplishTicket(
            $ticket,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => "Accomplishment report for ticket {$ticket->ticket_number} has been submitted.",
            'data' => new TicketAccomplishmentResource($accomplishmentReport->load('accomplishedBy', 'photos')),
        ], Response::HTTP_CREATED);
    }

    /*
     * Evaluates an accomplishment report (APPROVED closes the ticket, REJECTED returns to IN_PROGRESS).
     */
    public function verify(VerifyAccomplishmentRequest $request, Ticket $ticket, TicketAccomplishment $accomplishment): JsonResponse
    {
        Gate::authorize('verify', $ticket);

        $verifiedAccomplishment = $this->ticketService->verifyAccomplishment(
            $ticket,
            $accomplishment,
            $request->validated(),
            $request->user()
        );

        $statusAction = strtolower((string) $request->validated('status'));

        return response()->json([
            'success' => true,
            'message' => "Accomplishment report successfully {$statusAction}.",
            'data' => new TicketAccomplishmentResource($verifiedAccomplishment),
        ], Response::HTTP_OK);
    }
}
