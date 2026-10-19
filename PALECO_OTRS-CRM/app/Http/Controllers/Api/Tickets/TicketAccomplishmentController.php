<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Actions\Accomplishments\SubmitAccomplishment;
use App\Actions\Accomplishments\VerifyAccomplishment;
use App\Http\Controllers\Api\Concerns\ResolvesClientTimestamp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\SubmitAccomplishmentReportRequest;
use App\Http\Requests\Tickets\VerifyAccomplishmentRequest;
use App\Http\Resources\Api\TicketAccomplishmentResource;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Services\Tickets\TicketAccomplishmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/*
 * Manages the retrieval, submission, and supervisory verification of ticket accomplishment reports.
 */
class TicketAccomplishmentController extends Controller
{
    use ResolvesClientTimestamp;

    public function __construct(
        protected TicketAccomplishmentService $ticketAccomplishmentService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves all accomplishment reports submitted for a ticket.
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $accomplishments = $this->ticketAccomplishmentService->getAccomplishments($ticket);

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

        $loadedAccomplishment = $this->ticketAccomplishmentService->getAccomplishmentDetails($accomplishment, $ticket);

        return response()->json([
            'success' => true,
            'data' => new TicketAccomplishmentResource($loadedAccomplishment),
        ], Response::HTTP_OK);
    }

    // --- MUTATING METHODS ---

    /*
     * Submits a new accomplishment report with photo evidence using SubmitAccomplishment action.
     */
    public function store(
        SubmitAccomplishmentReportRequest $request,
        Ticket $ticket,
        SubmitAccomplishment $submitAccomplishment
    ): JsonResponse {
        Gate::authorize('accomplish', $ticket);

        $clientTimestamp = $this->resolveClientTimestamp($request, $ticket);
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');

        $accomplishmentReport = $submitAccomplishment->execute(
            $ticket,
            $request->user(),
            $request->validated(),
            $clientTimestamp,
            $idempotencyKey
        );

        return response()->json([
            'success' => true,
            'message' => "Accomplishment report for ticket {$ticket->ticket_number} has been submitted.",
            'data' => new TicketAccomplishmentResource($accomplishmentReport->load('accomplishedBy', 'photos')),
        ], Response::HTTP_CREATED);
    }

    /*
     * Evaluates an accomplishment report using VerifyAccomplishment action.
     */
    public function verify(
        VerifyAccomplishmentRequest $request,
        Ticket $ticket,
        TicketAccomplishment $accomplishment,
        VerifyAccomplishment $verifyAccomplishment
    ): JsonResponse {
        Gate::authorize('verify', $ticket);

        $verifiedAccomplishment = $verifyAccomplishment->execute(
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
