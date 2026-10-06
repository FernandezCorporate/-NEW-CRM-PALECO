<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use App\Services\Web\Dashboard\DashboardService;
use Illuminate\View\View;

/*
 * Handles the primary landing interface for CWD Officers.
 * Serves as the operational entry point into the utility complaint management backend.
 */
class CwdDashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Renders the main CWD officer dashboard view with ticket status overviews.
     */
    public function index(): View
    {
        return view('cwd.pages.dashboard', [
            'overview' => $this->dashboardService->ticketOverview(),
        ]);
    }
}
