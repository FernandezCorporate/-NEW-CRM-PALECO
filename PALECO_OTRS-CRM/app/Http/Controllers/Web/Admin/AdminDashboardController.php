<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Web\Dashboard\DashboardService;

/*
 * Handles the primary landing interface for administrators.
 * Serves as the entry point into the administrative backend.
 */
class AdminDashboardController extends Controller
{
    /*
     * Renders the main administrator dashboard view.
     */
    public function index(DashboardService $dashboard)
    {
        return view('admin.pages.dashboard', [
            'overview' => $dashboard->ticketOverview(),
            'summary' => $dashboard->adminSummary(),
        ]);
    }
}
