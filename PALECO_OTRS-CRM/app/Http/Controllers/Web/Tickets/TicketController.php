<?php

namespace App\Http\Controllers\Web\Tickets;

use App\Concerns\ResolvesSmartRedirects;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreChildTicketRequest;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 * Manages the core service ticket lifecycle for web users (CWD Officers & Read-Only Administrators).
 * Handles querying the ticket registry, detailed ticket inspection, and processing incoming complaints.
 */
class TicketController extends Controller
{
    use ResolvesSmartRedirects;

    public function __construct(
        protected TicketService $ticketService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves and renders the Ticket Management Dashboard.
     * Accessible by both CWD Officers (with mutation controls) and Administrators (read-only).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Ticket::class);

        session()->put('ticket_list_url', $request->fullUrl());

        $result = $this->ticketService->getTicketList($request);

        return view('shared.pages.ticketManagement', $result);
    }

    /*
     * Renders the comprehensive Ticket Details page with loaded relationship histories.
     * Accessible by both CWD Officers and Administrators.
     */
    public function show(Ticket $ticket): View
    {
        Gate::authorize('webView', $ticket);

        $result = $this->ticketService->getTicketDetails($ticket);

        return view('shared.pages.ticketDetails', $result);
    }

    // --- FORM METHODS (MUTATION - CWD ONLY) ---

    /*
     * Renders the dynamic Ticket Creation Form, populating necessary dropdowns.
     */
    public function ticketForm(Request $request): View
    {
        Gate::authorize('ticketForm', Ticket::class);

        $result = $this->ticketService->loadTicketForm();
        $result['source'] = $request->query('source');

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

    // --- ACTION METHODS (MUTATION - CWD ONLY) ---

    /*
     * Validates and processes the creation of a new utility service ticket.
     */
    public function store(StoreTicketRequest $request): RedirectResponse
    {
        Gate::authorize('create', Ticket::class);

        $ticket = $this->ticketService->createCwdTicket($request->validated());

        $destination = $this->resolveSmartRedirect($request, 'cwd.tickets', [
            'dashboard' => 'cwd.dashboard',
            'list' => 'cwd.tickets',
        ]);

        return redirect($destination)
            ->with('success', "Service Ticket {$ticket->ticket_number} successfully registered and queued.");
    }

    /*
     * Stores a new linked child ticket associated with the specified parent ticket.
     */
    public function storeChild(StoreChildTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('createChild', $ticket);

        $childTicket = $this->ticketService->createManualChildTicket($ticket, $request->validated());

        return redirect()->route('cwd.tickets.show', $childTicket)
            ->with('success', "Child Ticket {$childTicket->ticket_number} successfully registered under parent ticket {$ticket->ticket_number}.");
    }
}

