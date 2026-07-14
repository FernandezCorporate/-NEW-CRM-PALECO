<?php

namespace App\Http\Controllers\Cwd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cwd\StoreTicketRequest;
use App\Services\Cwd\TicketService;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\Department;
use App\Enums\ComplaintSources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    /**
     * Display the Ticket Management Dashboard with Search, Filter, and Sort capabilities.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Ticket::class);

        // Eloquent chain utilizing the model scopes for clean abstraction
        $tickets = Ticket::with(['category', 'department', 'creator'])
            ->search($request->search)
            ->filterByCategory($request->filter)
            ->sort($request->sort)
            ->paginate(10)
            ->withQueryString();
        
        $categories = TicketCategory::orderBy('category_name')->get();
    
        return view('cwd.pages.ticketManagement', compact('tickets', 'categories'));
    }

    /**
     * Render the Ticket Creation Form.
     */
    public function ticketForm(?Ticket $ticket = null)
    {
        Gate::authorize('ticketForm', $ticket ?? Ticket::class);

        $sources = ComplaintSources::cases();
        $categories = TicketCategory::orderBy('category_name')->get();
        $departments = Department::orderBy('dept_name')->get();

        return view('cwd.forms.ticketForm', compact('ticket', 'sources', 'categories', 'departments'));
    }

    /**
     * Process the submission of a new CWD Ticket.
     */
    public function store(StoreTicketRequest $request, TicketService $ticketService)
    {
        $ticket = $ticketService->createCwdTicket($request->validated());

        return redirect()->route('cwd.tickets')
            ->with('success', "Service Ticket {$ticket->ticket_number} successfully registered and queued.");
    }
}