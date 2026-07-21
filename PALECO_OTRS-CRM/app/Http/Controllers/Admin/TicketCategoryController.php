<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use App\Http\Requests\Admin\TicketCategory\StoreTicketCategoryRequest;
use App\Http\Requests\Admin\TicketCategory\UpdateTicketCategoryRequest;

use App\Services\Admin\TicketCategoryService;

use App\Models\TicketCategory;

/*
 * Manages the taxonomy of service tickets within the system.
 * Handles the creation, modification, and archiving of distinct ticket classifications.
 */
class TicketCategoryController extends Controller
{
    /*
     * Injects the TicketCategoryService to handle category-related logic.
     */
    public function __construct(protected TicketCategoryService $categoryService) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves and renders the paginated list of ticket categories for the management dashboard.
     * Stores the current URL in the session to allow returning to the exact filtered/paginated state.
     */
    public function viewAny(Request $request)
    {
        Gate::authorize('viewAny', TicketCategory::class);

        $categories = $this->categoryService->getDashboardCategories($request->all());

        session()->put('category_list_url', $request->fullUrl());

        return view('admin.pages.ticketCategoryManagement', compact('categories'));
    }

    /*
     * Retrieves and renders the detailed profile of a specific ticket category.
     */
    public function show(Request $request, TicketCategory $category)
    {
        Gate::authorize('view', clone $category);
        
        return view('admin.pages.ticketCategoryDetails', compact('category'));
    }

    // --- FORM METHODS ---

    /*
     * Renders the unified form used for both creating and updating ticket categories.
     */
    public function ticketCategoryForm(?TicketCategory $category = null)
    {
        Gate::authorize('ticketCategoryForm', $category ?? TicketCategory::class);
        
        return view('admin.forms.ticketCategoryForm', compact('category'));
    }

    // --- MUTATING METHODS ---

    /*
     * Processes validated request data to store a new ticket category in the database.
     */
    public function store(StoreTicketCategoryRequest $request)
    {
        Gate::authorize('create', TicketCategory::class);
        
        TicketCategory::create($request->validated());
        
        return redirect()->route('admin.ticketCategories')->with('success', 'Ticket category created successfully.');
    }

    /*
     * Processes validated request data to commit updates to an existing ticket category.
     * Redirects back to the originating view (dashboard or details page) based on the query parameter.
     */
    public function update(UpdateTicketCategoryRequest $request, TicketCategory $category)
    {
        Gate::authorize('update', $category);

        $wasUpdated = $this->categoryService->updateCategory($category, $request->validated());

        $redirectRoute = $request->query('source') === 'details' 
            ? route('admin.ticketCategories.show', $category) 
            : session('category_list_url', route('admin.ticketCategories'));

        if (!$wasUpdated) {
            return redirect($redirectRoute)->with('info', 'No changes were made to the category.');
        }

        return redirect($redirectRoute)->with('success', 'Ticket category updated successfully.');
    }

    // --- DESTRUCTIVE & STATE METHODS ---

    /*
     * Renders the confirmation prompt for archiving or permanently deleting a ticket category.
     */
    public function deleteConfirm(Request $request, TicketCategory $category)
    {
        $isForceDelete = $request->routeIs('admin.ticketCategories.forceDeleteConfirm');
        $category = $isForceDelete ? TicketCategory::onlyTrashed()->findOrFail($category->id) : $category;

        Gate::authorize('deleteConfirm', clone $category);

        $title = $isForceDelete ? 'Permanently Delete Category' : 'Archive Category';
        
        return view('admin.prompts.ticketCategoryDeleteConfirm', compact('category', 'title', 'isForceDelete'));
    }

    /*
     * Executes a soft delete to safely archive the specified ticket category.
     */
    public function archive(TicketCategory $category)
    {
        Gate::authorize('archive', $category);
        $category->delete();
        return redirect()->route('admin.ticketCategories')->with('success', 'Category archived successfully.');
    }

    /*
     * Recovers a previously archived ticket category back to active status.
     */
    public function restore($id) 
    {
        Gate::authorize('restore', TicketCategory::class);

        $result = $this->categoryService->restoreCategory($id);

        if (!$result['success']) {
            return redirect()->route('admin.ticketCategories')->with('error', $result['message']);
        }

        return redirect()->route('admin.ticketCategories')->with('success', $result['message']);
    }

    /*
     * Permanently eradicates the ticket category record from the database.
     * Delegates to the service layer to ensure it isn't orphaned from existing tickets.
     */
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