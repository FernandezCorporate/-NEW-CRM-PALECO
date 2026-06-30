<?php

namespace App\Http\Controllers\Cwd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CwdDashboardController extends Controller
{
    public function index()
    {
        return view('cwd.pages.dashboard');
    }
}
