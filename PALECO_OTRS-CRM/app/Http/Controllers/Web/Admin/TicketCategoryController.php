<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\StoreTicketCategoryRequest;
use App\Http\Requests\Categories\UpdateTicketCategoryRequest;
use App\Models\TicketCategory;
use App\Services\Categories\TicketCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/*
 * Manages the lifecycle and web interfaces for Ticket Categories.
 * Handles viewing, creating, updating, archiving, and purging categories.
 */
class TicketCategoryController extends Controller
{
    public function __construct(
        protected TicketCategoryService $categoryService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves and renders the paginated list of ticket categories for the management dashboard.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', TicketCategory::class);

        $categories = $this->categoryService->getDashboardCategories($request->all());
        session()->put('category_list_url', $request->fullUrl());

        return view('admin.pages.ticketCategoryManagement', compact('categories'));
    }

    /*
     * Retrieves and renders the detailed profile and linked service tickets of a specific category.
     */
    public function show(Request $request, TicketCategory $category)
    {
        Gate::authorize('view', clone $category);

        $details = $this->categoryService->getCategoryDetails($category);

        return view('admin.pages.ticketCategoryDetails', array_merge(['category' => $category], $details));
    }

    // --- FORM METHODS ---

    /*
     * Renders the unified form used for both creating and modifying ticket categories.
     */
    public function ticketCategoryForm(?TicketCategory $category = null)
    {
        if ($category?->trashed()) {
            return redirect()->route('admin.ticketCategories')->with('error', 'The category you are trying to edit has been archived by another administrator.');
        }

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
     */
    public function update(UpdateTicketCategoryRequest $request, TicketCategory $category)
    {
        if ($category->trashed()) {
            return redirect()->route('admin.ticketCategories')->with('error', 'Failed to save changes. The category was recently archived by another administrator.');
        }

        Gate::authorize('update', $category);

        $result = $this->categoryService->updateCategory($category, $request->validated());

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['message'])->withInput();
        }

        $redirectRoute = $request->query('source') === 'details'
            ? route('admin.ticketCategories.show', $category)
            : route('admin.ticketCategories');

        if (! $result['changed']) {
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

        if ($isForceDelete && ! $category->trashed()) {
            return redirect()->route('admin.ticketCategories')->with('error', 'This category was restored by another administrator and must be archived before permanent deletion.');
        }

        if (! $isForceDelete && $category->trashed()) {
            return redirect()->route('admin.ticketCategories')->with('info', 'This category has already been archived.');
        }

        Gate::authorize('deleteConfirm', clone $category);

        $title = $isForceDelete ? 'Permanently Delete Category' : 'Archive Category';

        return view('admin.prompts.ticketCategoryDeleteConfirm', compact('category', 'title', 'isForceDelete'));
    }

    /*
     * Executes a soft delete to safely archive the specified ticket category.
     */
    public function archive(TicketCategory $category)
    {
        if ($category->trashed()) {
            return redirect()->route('admin.ticketCategories')->with('error', 'This category has already been archived.');
        }

        Gate::authorize('archive', $category);

        $result = $this->categoryService->archiveCategory($category);

        if (! $result['success']) {
            return redirect()->route('admin.ticketCategories')->with('error', $result['message']);
        }

        return redirect()->route('admin.ticketCategories')->with('success', $result['message']);
    }

    /*
     * Recovers a previously archived ticket category back to active status.
     */
    public function restore($id)
    {
        Gate::authorize('restore', TicketCategory::class);

        $result = $this->categoryService->restoreCategory($id);

        if (! $result['success']) {
            return redirect()->route('admin.ticketCategories')->with('error', $result['message']);
        }

        return redirect()->route('admin.ticketCategories')->with('success', $result['message']);
    }

    /*
     * Permanently purges the ticket category from the database.
     */
    public function destroy($id)
    {
        Gate::authorize('forceDelete', TicketCategory::class);

        $result = $this->categoryService->forceDeleteCategory($id);

        if (! $result['success']) {
            return redirect()->route('admin.ticketCategories')->with('error', $result['message']);
        }

        return redirect()->route('admin.ticketCategories')->with('success', $result['message']);
    }
}
