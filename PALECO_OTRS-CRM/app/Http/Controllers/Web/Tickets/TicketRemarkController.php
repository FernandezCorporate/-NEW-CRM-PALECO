<?php

namespace App\Http\Controllers\Web\Tickets;

use App\Actions\Tickets\AddTicketRemark;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketRemarkRequest;
use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Services\Tickets\TicketRemarkService;
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
    public function store(StoreTicketRemarkRequest $request, Ticket $ticket, AddTicketRemark $addTicketRemark): RedirectResponse
    {
        Gate::authorize('create', [TicketRemark::class, $ticket]);

        $addTicketRemark->execute(
            $ticket,
            $request->user(),
            $request->validated()
        );

        return back()->with('success', 'Remark added successfully.');
    }
}
