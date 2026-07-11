<?php

namespace App\Http\Controllers\Cwd;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Ticket::class);

        $tickets = Ticket::query();
        $tickets = $tickets->paginate(10);
    
        return view('cwd.pages.ticketManagement', compact('tickets'));
    }
}
