<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Services\Api\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
 * Manages the service ticket inbox for mobile application users.
 * Handles querying the ticket registry restricted to the authenticated user's department.
 */
class TicketController extends Controller
{
    public function __construct(protected TicketService $ticketService) {}

    // --- VIEW METHODS ---

    /*
     * Fetch and return the paginated ticket inbox for the authenticated user's department.
     */
    public function index(Request $request): JsonResponse
    {
        $tickets = $this->ticketService->getDepartmentTickets(
            $request->user(), 
            $request->only(['search', 'category', 'status', 'sort'])
        );

        return response()->json([
            'success' => true,
            'status'  => 200,
            'data'    => $tickets
        ]);
    }
}