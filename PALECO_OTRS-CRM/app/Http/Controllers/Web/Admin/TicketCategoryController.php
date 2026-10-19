<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Categories\ArchiveTicketCategory;
use App\Actions\Categories\CreateTicketCategory;
use App\Actions\Categories\ForceDeleteTicketCategory;
use App\Actions\Categories\RestoreTicketCategory;
use App\Actions\Categories\UpdateTicketCategory;
use App\Concerns\ResolvesSmartRedirects;
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
    use ResolvesSmartRedirects;

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
     * Processes validated request data to store a new ticket category using CreateTicketCategory action.
     */
    public function store(StoreTicketCategoryRequest $request, CreateTicketCategory $createCategory)
    {
        Gate::authorize('create', TicketCategory::class);

        $createCategory->execute($request->validated());

        return redirect()->route('admin.ticketCategories')->with('success', 'Ticket category created successfully.');
    }

    /*
     * Processes validated request data to commit updates to an existing ticket category using UpdateTicketCategory action.
     */
    public function update(UpdateTicketCategoryRequest $request, TicketCategory $category, UpdateTicketCategory $updateCategory)
    {
        if ($category->trashed()) {
            return redirect()->route('admin.ticketCategories')->with('error', 'Failed to save changes. The category was recently archived by another administrator.');
        }

        Gate::authorize('update', $category);

        $result = $updateCategory->execute($category, $request->validated());

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['message'])->withInput();
        }

        $redirectRoute = $this->resolveSmartRedirect($request, 'admin.ticketCategories', [
            'details' => ['admin.ticketCategories.show', ['category' => $category]],
        ]);

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
     * Executes a soft delete to safely archive the specified ticket category using ArchiveTicketCategory action.
     */
    public function archive(TicketCategory $category, ArchiveTicketCategory $archiveCategory)
    {
        if ($category->trashed()) {
            return redirect()->route('admin.ticketCategories')->with('error', 'This category has already been archived.');
        }

        Gate::authorize('archive', $category);

        $result = $archiveCategory->execute($category);

        if (! $result['success']) {
            return redirect()->route('admin.ticketCategories')->with('error', $result['message']);
        }

        return redirect()->route('admin.ticketCategories')->with('success', $result['message']);
    }

    /*
     * Recovers a previously archived ticket category back to active status using RestoreTicketCategory action.
     */
    public function restore($id, RestoreTicketCategory $restoreCategory)
    {
        Gate::authorize('restore', TicketCategory::class);

        $result = $restoreCategory->execute($id);

        if (! $result['success']) {
            return redirect()->route('admin.ticketCategories')->with('error', $result['message']);
        }

        return redirect()->route('admin.ticketCategories')->with('success', $result['message']);
    }

    /*
     * Permanently purges the ticket category from the database using ForceDeleteTicketCategory action.
     */
    public function destroy($id, ForceDeleteTicketCategory $forceDeleteCategory)
    {
        Gate::authorize('forceDelete', TicketCategory::class);

        $result = $forceDeleteCategory->execute($id);

        if (! $result['success']) {
            return redirect()->route('admin.ticketCategories')->with('error', $result['message']);
        }

        return redirect()->route('admin.ticketCategories')->with('success', $result['message']);
    }
}
