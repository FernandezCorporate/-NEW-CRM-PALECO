<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Cwd\TicketEndorsement\EndorsementDecisionRequest;
use App\Models\TicketEndorsement;
use App\Services\Web\Cwd\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketEndorsementController extends Controller
{
    public function __construct(protected TicketService $ticketService) { }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', TicketEndorsement::class);
        
        $result = $this->ticketService->getEndorsementList($request);

        return view('cwd.pages.endorsementDashboard', $result);
    }

    public function show(Request $request, TicketEndorsement $endorsement)
    {
        Gate::authorize('view', $endorsement);

        $result = $this->ticketService->getEndorsementDetails($endorsement);

        return view('cwd.pages.endorsementDetails', $result);
    }

    public function decide(EndorsementDecisionRequest $request, TicketEndorsement $endorsement)
    {
        Gate::authorize('decide', $endorsement);

        $result = $this->ticketService->verifyEndorsement($request->validated(), $endorsement);

        // Safely bounce back if the Race Condition check failed in the service
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        // Standard success flow
        return redirect()->route('cwd.endorsements')->with('success', 'Endorsement processed successfully.');
    }
}