<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use App\Models\TicketEscalation;
use App\Services\Web\Cwd\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketEscalationController extends Controller
{
    public function __construct(protected TicketService $ticketService) { }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', TicketEscalation::class);
        
        $result = $this->ticketService->getEscalationList($request);

        return view('cwd.pages.escalationDashboard', $result);
    }

    public function show(Request $request, TicketEscalation $escalation)
    {
        Gate::authorize('view', $escalation);

        $result = $this->ticketService->getEscalationDetails($escalation);

        return view('cwd.pages.escalationDetails', $result);
    }
}
