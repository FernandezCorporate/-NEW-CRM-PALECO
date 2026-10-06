<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Web\Dashboard\DashboardService;
use Illuminate\View\View;

/*
 * Handles the primary landing interface for administrators.
 * Serves as the executive overview into system accounts, teams, and complaint workloads.
 */
class AdminDashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Renders the main administrator dashboard view with metrics and workload summaries.
     */
    public function index(): View
    {
        return view('admin.pages.dashboard', [
            'overview' => $this->dashboardService->ticketOverview(),
            'summary' => $this->dashboardService->adminSummary(),
        ]);
    }
}
