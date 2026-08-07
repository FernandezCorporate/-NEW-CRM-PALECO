<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/*
 * Handles the primary landing interface for CWD Officers.
 * Serves as the entry point into the utility management backend.
 */
class CwdDashboardController extends Controller
{
    /*
     * Renders the main CWD officer dashboard view.
     */
    public function index()
    {
        return view('cwd.pages.dashboard');
    }
}