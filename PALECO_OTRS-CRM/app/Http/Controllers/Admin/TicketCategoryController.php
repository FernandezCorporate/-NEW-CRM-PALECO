<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TicketCategory\StoreTicketCategoryRequest;
use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketCategoryController extends Controller
{
    public function viewAny(Request $request)
    {
        Gate::authorize('viewAny', TicketCategory::class);

        return view('admin.pages.ticketCategoryManagement');
    }

    public function ticketCategoryForm(?TicketCategory $category = null)
    {
        Gate::authorize('ticketCategoryForm', TicketCategory::class);

        return view('admin.forms.ticketCategoryForm', compact('category'));
    }

    public function store(StoreTicketCategoryRequest $request)
    {
        Gate::authorize('create', TicketCategory::class);

        $validatedData = $request->validated();

        TicketCategory::create($validatedData);

        return redirect()->route('admin.ticketCategories')->with('success', 'Ticket category created successfully.');
    }
}
