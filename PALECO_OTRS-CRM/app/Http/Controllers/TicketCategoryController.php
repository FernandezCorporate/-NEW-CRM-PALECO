<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TicketCategoryController extends Controller
{
    public function viewAny(Request $request)
    {
        return view('admin.pages.ticketCategoryManagement');
    }
}
