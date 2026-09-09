<?php

namespace App\Http\Controllers\Api\Remarks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Remarks\StoreTicketRemarkRequest;
use App\Http\Resources\Api\TicketRemarkResource;
use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Services\Api\Remarks\TicketRemarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/*
 * Manages the retrieval and creation of ticket remarks for the mobile application.
 */
class TicketRemarkController extends Controller
{
    public function __construct(
        protected TicketRemarkService $remarkService
    ) {}

    /*
     * Retrieves the filtered timeline of remarks for a ticket.
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $remarks = $this->remarkService->getTimeline($ticket, $request->user());

        return response()->json([
            'success' => true,
            'data'    => TicketRemarkResource::collection($remarks)
        ], 200);
    }

    /*
     * Validates and processes the creation of a new remark.
     */
    public function store(StoreTicketRemarkRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('mobileCreate', [TicketRemark::class, $ticket]);

        $remark = $this->remarkService->createRemark(
            $ticket,
            $request->user(),
            $request->validated()
        );

        $remark->load(['author.role']);

        return response()->json([
            'success' => true,
            'message' => 'Remark posted successfully.',
            'data'    => new TicketRemarkResource($remark)
        ], 201);
    }
}