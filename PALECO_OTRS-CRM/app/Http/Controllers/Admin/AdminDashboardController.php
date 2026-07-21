<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/*
 * Handles the primary landing interface for administrators.
 * Serves as the entry point into the administrative backend.
 */
class AdminDashboardController extends Controller
{
    /*
     * Renders the main administrator dashboard view.
     */
    public function index()
    {
        return view('admin.pages.dashboard');
    }
}