<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TicketCategory\StoreTicketCategoryRequest;
use App\Http\Requests\Admin\TicketCategory\UpdateTicketCategoryRequest;
use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketCategoryController extends Controller
{
    public function viewAny(Request $request)
    {
        Gate::authorize('viewAny', TicketCategory::class);

        $categories = TicketCategory::query();

        if ($request->filled('search')) {
            $categories = $categories->search($request->search);
        }

        if ($request->filled('sort')) {
            $categories = $categories->sort($request->sort);
        } else {
            $categories = $categories->latest();
        }

        if ($request->input('filter') === 'archived') {
            $categories = $categories->onlyTrashed();
        }

        session()->put('category_list_url', $request->fullUrl());

        $categories = $categories->paginate(10)->withQueryString();

        return view('admin.pages.ticketCategoryManagement', compact('categories'));
    }

    public function show(Request $request, TicketCategory $category)
    {
        Gate::authorize('view', clone $category);
        
        // This is a placeholder for when tickets are built later
        return view('admin.pages.ticketCategoryDetails', compact('category'));
    }

    public function ticketCategoryForm(?TicketCategory $category = null)
    {
        Gate::authorize('ticketCategoryForm', $category ?? TicketCategory::class);
        
        return view('admin.forms.ticketCategoryForm', compact('category'));
    }

    public function store(StoreTicketCategoryRequest $request)
    {
        Gate::authorize('create', TicketCategory::class);
        TicketCategory::create($request->validated());
        return redirect()->route('admin.ticketCategories')->with('success', 'Ticket category created successfully.');
    }

    public function update(UpdateTicketCategoryRequest $request, TicketCategory $category)
    {
        Gate::authorize('update', $category);

        $category->fill($request->validated());

        $redirectRoute = $request->query('source') === 'details' 
            ? route('admin.ticketCategories.show', $category) 
            : session('category_list_url', route('admin.ticketCategories'));

        if ($category->isClean()) {
            return redirect($redirectRoute)->with('info', 'No changes were made to the category.');
        }

        $category->save();
        return redirect($redirectRoute)->with('success', 'Ticket category updated successfully.');
    }

    public function deleteConfirm(Request $request, TicketCategory $category)
    {
        $isForceDelete = $request->routeIs('admin.ticketCategories.forceDeleteConfirm');
        $category = $isForceDelete ? TicketCategory::onlyTrashed()->findOrFail($category->id) : $category;

        Gate::authorize('deleteConfirm', clone $category);

        $title = $isForceDelete ? 'Permanently Delete Category' : 'Archive Category';
        
        return view('admin.prompts.ticketCategoryDeleteConfirm', compact('category', 'title', 'isForceDelete'));
    }

    public function archive(TicketCategory $category)
    {
        Gate::authorize('archive', $category);
        $category->delete();
        return redirect()->route('admin.ticketCategories')->with('success', 'Category archived successfully.');
    }

    public function restore($id) 
    {
        $category = TicketCategory::onlyTrashed()->findOrFail($id);
        Gate::authorize('restore', clone $category);

        $nameExists = TicketCategory::where('category_name', $category->category_name)->exists();

        if ($nameExists) {
            return redirect()->route('admin.ticketCategories')->with('error', 'Cannot restore category. An active category with the same name already exists.');
        }

        $category->restore();
        return redirect()->route('admin.ticketCategories')->with('success', 'Category restored successfully.');
    }

    public function destroy($id)
    {
        $category = TicketCategory::onlyTrashed()->findOrFail($id);
        Gate::authorize('forceDelete', clone $category);

        // Feature 4 Requirement: Add ticket count check here in the future
        // if ($category->tickets()->count() > 0) {
        //     return redirect()->back()->with('error', 'Cannot delete: Tickets are still assigned to this category.');
        // }

        $category->forceDelete();
        return redirect()->route('admin.ticketCategories')->with('success', 'Category permanently deleted.');
    }
}