<?php

namespace App\Http\Controllers\Web\Tickets;

use App\Actions\Tickets\AddTicketRemark;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketRemarkRequest;
use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Services\Tickets\TicketRemarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/*
 * Manages the submission and storage of ticket remarks within the web portals.
 */
class TicketRemarkController extends Controller
{
    public function __construct(
        protected TicketRemarkService $remarkService
    ) {}

    // --- MUTATING METHODS ---

    /*
     * Validates and attaches a new communication remark using AddTicketRemark action.
     */
    public function store(StoreTicketRemarkRequest $request, Ticket $ticket, AddTicketRemark $addTicketRemark): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', [TicketRemark::class, $ticket]);

        $remark = $addTicketRemark->execute(
            $ticket,
            $request->user(),
            $request->validated()
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Remark added successfully.',
                'data' => [
                    'id' => $remark->id,
                    'body' => $remark->body,
                    'is_internal' => (bool) $remark->is_internal,
                ],
            ], 201);
        }

        return back()->with('success', 'Remark added successfully.');
    }
}
