<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use App\Http\Requests\Admin\TicketCategory\StoreTicketCategoryRequest;
use App\Http\Requests\Admin\TicketCategory\UpdateTicketCategoryRequest;

use App\Services\Admin\TicketCategoryService;

use App\Models\TicketCategory;

class TicketCategoryController extends Controller
{
    public function __construct(protected TicketCategoryService $categoryService) {}

    public function viewAny(Request $request)
    {
        Gate::authorize('viewAny', TicketCategory::class);

        $categories = $this->categoryService->getDashboardCategories($request->all());
        session()->put('category_list_url', $request->fullUrl());

        return view('admin.pages.ticketCategoryManagement', compact('categories'));
    }

    public function show(Request $request, TicketCategory $category)
    {
        Gate::authorize('view', clone $category);
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

        $result = $this->categoryService->updateCategory($category, $request->validated());

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message'])->withInput();
        }

        $redirectRoute = $request->query('source') === 'details' 
            ? route('admin.ticketCategories.show', $category) 
            : session('category_list_url', route('admin.ticketCategories'));

        if (!$result['changed']) {
            return redirect($redirectRoute)->with('info', 'No changes were made to the category.');
        }

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
        
        $result = $this->categoryService->archiveCategory($category);
        
        if (!$result['success']) {
            return redirect()->route('admin.ticketCategories')->with('error', $result['message']);
        }

        return redirect()->route('admin.ticketCategories')->with('success', $result['message']);
    }

    public function restore($id) 
    {
        Gate::authorize('restore', TicketCategory::class);

        $result = $this->categoryService->restoreCategory($id);

        if (!$result['success']) {
            return redirect()->route('admin.ticketCategories')->with('error', $result['message']);
        }

        return redirect()->route('admin.ticketCategories')->with('success', $result['message']);
    }

    public function destroy($id)
    {
        Gate::authorize('forceDelete', TicketCategory::class);

        $result = $this->categoryService->permanentlyDeleteCategory($id);

        if (!$result['success']) {
            return redirect()->route('admin.ticketCategories')->with('error', $result['message']);
        }
        
        return redirect()->route('admin.ticketCategories')->with('success', $result['message']);
    }
}