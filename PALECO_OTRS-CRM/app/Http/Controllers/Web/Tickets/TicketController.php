<?php

namespace App\Http\Controllers\Web\Tickets;

use App\Actions\Tickets\CancelTicket;
use App\Actions\Tickets\CreateCwdTicket;
use App\Actions\Tickets\CreateManualChildTicket;
use App\Actions\Tickets\ForceCloseTicket;
use App\Actions\Tickets\UpdateTicketIntake;
use App\Concerns\ResolvesSmartRedirects;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\CancelTicketRequest;
use App\Http\Requests\Tickets\ForceCloseTicketRequest;
use App\Http\Requests\Tickets\StoreChildTicketRequest;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\UpdateTicketIntakeRequest;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
     * Renders the dynamic Ticket Intake Edit Form for modifying clerical data while the ticket is OPEN.
     */
    public function editForm(Request $request, Ticket $ticket): View
    {
        Gate::authorize('update', $ticket);

        $result = $this->ticketService->loadTicketEditForm($ticket);
        $result['source'] = $request->query('source');

        return view('cwd.forms.ticketEditForm', $result);
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
     * Validates and processes the creation of a new utility service ticket using CreateCwdTicket action.
     */
    public function store(StoreTicketRequest $request, CreateCwdTicket $createCwdTicket): RedirectResponse
    {
        Gate::authorize('create', Ticket::class);

        $ticket = $createCwdTicket->execute($request->validated());

        $destination = $this->resolveSmartRedirect($request, 'cwd.tickets', [
            'dashboard' => 'cwd.dashboard',
            'list' => 'cwd.tickets',
        ]);

        return redirect($destination)
            ->with('success', "Service Ticket {$ticket->ticket_number} successfully registered and queued.");
    }

    /*
     * Validates and updates clerical intake details on an existing Open ticket using UpdateTicketIntake action.
     */
    public function update(UpdateTicketIntakeRequest $request, Ticket $ticket, UpdateTicketIntake $updateTicketIntake): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $redirectUrl = route('cwd.tickets.show', array_filter([
            'ticket' => $ticket,
            'source' => $request->input('source'),
            'consumer' => $request->input('consumer'),
            'parent' => $request->input('parent'),
        ]));

        try {
            $result = $updateTicketIntake->execute($ticket, $request->validated());
        } catch (ValidationException $e) {
            $errorMessage = collect($e->errors())->flatten()->first() ?? 'Ticket can no longer be edited.';

            return redirect($redirectUrl)
                ->with('error', $errorMessage);
        }

        if (! $result['changed']) {
            return redirect($redirectUrl)
                ->with('info', "No changes were detected for ticket {$ticket->ticket_number}.");
        }

        return redirect($redirectUrl)
            ->with('success', "Ticket {$ticket->ticket_number} intake details successfully updated.");
    }

    /*
     * Stores a new linked child ticket associated with the specified parent ticket using CreateManualChildTicket action.
     */
    public function storeChild(StoreChildTicketRequest $request, Ticket $ticket, CreateManualChildTicket $createChildTicket): RedirectResponse
    {
        Gate::authorize('createChild', $ticket);

        $childTicket = $createChildTicket->execute($ticket, $request->validated());

        return redirect()->route('cwd.tickets.show', $childTicket)
            ->with('success', "Child Ticket {$childTicket->ticket_number} successfully registered under parent ticket {$ticket->ticket_number}.");
    }

    /*
     * Renders full-page confirmation prompt for cancelling an open ticket.
     */
    public function cancelConfirm(Ticket $ticket): View
    {
        Gate::authorize('cancel', $ticket);

        return view('cwd.prompts.ticketCancelConfirm', [
            'ticket' => $ticket,
        ]);
    }

    /*
     * Validates and processes the cancellation of an open or assigned service ticket using CancelTicket action.
     */
    public function cancel(CancelTicketRequest $request, Ticket $ticket, CancelTicket $cancelTicket): RedirectResponse
    {
        Gate::authorize('cancel', $ticket);

        try {
            $cancelTicket->execute($ticket, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            $errorMessage = collect($e->errors())->flatten()->first() ?? 'Ticket cannot be cancelled.';

            return redirect()->route('cwd.tickets.show', $ticket)
                ->with('error', $errorMessage);
        }

        return redirect()->route('cwd.tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} has been successfully cancelled.");
    }

    /*
     * Renders full-page confirmation prompt for administratively force-closing an active ticket.
     */
    public function forceCloseConfirm(Ticket $ticket): View
    {
        Gate::authorize('forceClose', $ticket);

        return view('shared.prompts.ticketForceCloseConfirm', [
            'ticket' => $ticket,
        ]);
    }

    /*
     * Validates and processes administrative force-closure of an active service ticket using ForceCloseTicket action.
     */
    public function forceClose(ForceCloseTicketRequest $request, Ticket $ticket, ForceCloseTicket $forceCloseTicket): RedirectResponse
    {
        Gate::authorize('forceClose', $ticket);

        try {
            $forceCloseTicket->execute($ticket, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            $errorMessage = collect($e->errors())->flatten()->first() ?? 'Ticket cannot be force-closed.';

            return redirect()->route('cwd.tickets.show', $ticket)
                ->with('error', $errorMessage);
        }

        return redirect()->route('cwd.tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} has been administratively force-closed.");
    }
}
