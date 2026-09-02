<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Services\Web\Cwd\TicketService;
use Illuminate\Support\Facades\Gate;

class TicketAccomplishmentController extends Controller
{
    public function __construct(protected TicketService $ticketService) { }

    public function show(Ticket $ticket, TicketAccomplishment $accomplishment)
    {
        Gate::authorize('view', $accomplishment);

        if ($accomplishment->ticket_id !== $ticket->system_id) {
            abort(404, 'This accomplishment report does not belong to the requested ticket.');
        }

        // Store the loaded model in a custom variable
        $detailedAccomplishment = $this->ticketService->getAccomplishmentDetails($accomplishment);
        
        // Map the variables exactly as the Blade view expects them
        return view('cwd.pages.ticketAccomplishmentDetails', [
            'ticket'         => $ticket,
            'accomplishment' => $detailedAccomplishment
        ]);
    }

}
