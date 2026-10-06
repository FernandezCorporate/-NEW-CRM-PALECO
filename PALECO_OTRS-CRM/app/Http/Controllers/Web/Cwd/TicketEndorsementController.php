<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Cwd\TicketEndorsement\EndorsementDecisionRequest;
use App\Models\TicketEndorsement;
use App\Services\Web\Cwd\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 * Manages the review, presentation, and decision processing for cross-department ticket endorsements.
 */
class TicketEndorsementController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Renders the CWD endorsement queue showing tickets awaiting department transfer approval.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TicketEndorsement::class);

        $result = $this->ticketService->getEndorsementList($request);

        return view('cwd.pages.endorsementDashboard', $result);
    }

    /*
     * Renders comprehensive endorsement details including originating reasons and target departments.
     */
    public function show(Request $request, TicketEndorsement $endorsement): View
    {
        Gate::authorize('view', $endorsement);

        $result = $this->ticketService->getEndorsementDetails($endorsement);

        return view('cwd.pages.endorsementDetails', $result);
    }

    // --- MUTATING METHODS ---

    /*
     * Evaluates an endorsement decision (accepting reroutes the ticket, rejecting returns it).
     */
    public function decide(EndorsementDecisionRequest $request, TicketEndorsement $endorsement): RedirectResponse
    {
        Gate::authorize('decide', $endorsement);

        $result = $this->ticketService->verifyEndorsement($request->validated(), $endorsement);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('cwd.endorsements')->with('success', 'Endorsement processed successfully.');
    }
}
