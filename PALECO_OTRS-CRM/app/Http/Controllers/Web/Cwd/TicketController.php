<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use App\Http\Requests\Web\Cwd\StoreTicketRequest;

use App\Services\Web\Cwd\TicketService;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;

use App\Enums\ComplaintSources;

/*
 * Manages the core service ticket lifecycle for CWD Officers.
 * Handles querying the ticket registry and processing new incoming utility complaints.
 */
class TicketController extends Controller
{
    public function __construct(protected TicketService $ticketService) { }

    // --- VIEW METHODS ---

    /*
     * Retrieves and renders the Ticket Management Dashboard.
     * Utilizes Eloquent model scopes for robust Search, Filter, and Sort capabilities.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Ticket::class);

        $result = $this->ticketService->getTicketList($request);
    
        return view('cwd.pages.ticketManagement', $result);
    }

    // --- FORM METHODS ---

    /*
     * Renders the dynamic Ticket Creation Form, populating necessary dropdowns.
     */
    public function ticketForm()
    {
        Gate::authorize('ticketForm', $ticket ?? Ticket::class);

        $result = $this->ticketService->loadTicketForm();

        return view('cwd.forms.ticketForm', $result);
    }

    // --- MUTATING METHODS ---

    /*
     * Processes validated request data to register and queue a newly submitted service ticket.
     */
    public function store(StoreTicketRequest $request, TicketService $ticketService)
    {
        $ticket = $ticketService->createCwdTicket($request->validated());

        return redirect()->route('cwd.tickets')
            ->with('success', "Service Ticket {$ticket->ticket_number} successfully registered and queued.");
    }
}