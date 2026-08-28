<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Web\DashboardService;

/*
 * Handles the primary landing interface for CWD Officers.
 * Serves as the entry point into the utility management backend.
 */
class CwdDashboardController extends Controller
{
    /*
     * Renders the main CWD officer dashboard view.
     */
    public function index(DashboardService $dashboard)
    {
        return view('cwd.pages.dashboard', [
            'overview' => $dashboard->ticketOverview(),
        ]);
    }
}
