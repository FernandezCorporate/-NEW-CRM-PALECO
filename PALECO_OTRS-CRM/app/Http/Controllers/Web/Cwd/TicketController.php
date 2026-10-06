<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Cwd\StoreChildTicketRequest;
use App\Http\Requests\Web\Cwd\StoreTicketRequest;
use App\Models\Ticket;
use App\Services\Web\Cwd\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 * Manages the core service ticket lifecycle for CWD Officers.
 * Handles querying the ticket registry, detailed ticket inspection, and processing new incoming utility complaints.
 */
class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves and renders the Ticket Management Dashboard.
     * Utilizes Eloquent model scopes for robust Search, Filter, and Sort capabilities.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Ticket::class);

        $result = $this->ticketService->getTicketList($request);

        return view('cwd.pages.ticketManagement', $result);
    }

    /*
     * Renders the comprehensive Ticket Details page with loaded relationship histories.
     */
    public function show(Ticket $ticket): View
    {
        Gate::authorize('webView', $ticket);

        $result = $this->ticketService->getTicketDetails($ticket);

        return view('cwd.pages.ticketDetails', $result);
    }

    // --- FORM METHODS ---

    /*
     * Renders the dynamic Ticket Creation Form, populating necessary dropdowns.
     */
    public function ticketForm(): View
    {
        Gate::authorize('ticketForm', Ticket::class);

        $result = $this->ticketService->loadTicketForm();

        return view('cwd.forms.ticketForm', $result);
    }

    /*
     * Renders the child ticket creation form populated with parent ticket details and routing options.
     */
    public function childTicketForm(Ticket $ticket): View
    {
        Gate::authorize('createChild', $ticket);

        $result = $this->ticketService->loadChildTicketForm($ticket);

        return view('cwd.forms.childTicketForm', $result);
    }

    // --- MUTATING METHODS ---

    /*
     * Processes validated request data to register and queue a newly submitted service ticket.
     */
    public function store(StoreTicketRequest $request): RedirectResponse
    {
        Gate::authorize('create', Ticket::class);

        $ticket = $this->ticketService->createCwdTicket($request->validated());

        return redirect()->route('cwd.tickets')
            ->with('success', "Service Ticket {$ticket->ticket_number} successfully registered and queued.");
    }

    /*
     * Processes validated request data to create and attach a manual child ticket under a parent ticket.
     */
    public function storeChild(StoreChildTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('createChild', $ticket);

        $childTicket = $this->ticketService->createManualChildTicket($ticket, $request->validated());

        return redirect()->route('cwd.tickets.show', $childTicket)
            ->with('success', "Child Ticket {$childTicket->ticket_number} successfully registered under parent ticket {$ticket->ticket_number}.");
    }
}
