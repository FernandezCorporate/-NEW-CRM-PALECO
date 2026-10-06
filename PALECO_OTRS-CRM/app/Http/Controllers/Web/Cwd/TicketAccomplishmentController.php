<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Services\Web\Cwd\TicketService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 * Manages the presentation of ticket accomplishment reports within the CWD web portal.
 */
class TicketAccomplishmentController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Renders the detailed accomplishment submission including photos and verification status.
     */
    public function show(Ticket $ticket, TicketAccomplishment $accomplishment): View
    {
        Gate::authorize('view', $accomplishment);

        if ($accomplishment->ticket_id !== $ticket->system_id) {
            abort(404, 'This accomplishment report does not belong to the requested ticket.');
        }

        $detailedAccomplishment = $this->ticketService->getAccomplishmentDetails($accomplishment);

        return view('cwd.pages.ticketAccomplishmentDetails', [
            'ticket' => $ticket,
            'accomplishment' => $detailedAccomplishment,
        ]);
    }
}
