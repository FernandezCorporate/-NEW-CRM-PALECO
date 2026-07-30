<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use App\Http\Requests\Admin\Department\StoreDepartmentRequest;
use App\Http\Requests\Admin\Department\UpdateDepartmentRequest;

use App\Services\Admin\DepartmentService;

use App\Models\Department;

/*
 * Manages the lifecycle and web interfaces for system Departments.
 * Handles viewing, creating, modifying, and archiving department records.
 */
class DepartmentController extends Controller
{
    /*
     * Injects the DepartmentService to handle complex business logic.
     */
    public function __construct(protected DepartmentService $departmentService) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves and renders the paginated list of departments for the management dashboard.
     * Stores the current URL in the session to allow returning to the exact filtered/paginated state.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Department::class);

        $departments = $this->departmentService->getDashboardDepartments($request->all());
        
        session()->put('department_list_url', $request->fullUrl());

        return view('admin.pages.departmentManagement', compact('departments'));
    }

    /*
     * Retrieves and renders the detailed profile view of a specific department.
     */
    public function show(Request $request, Department $dept)
    {
        Gate::authorize('view', $dept);

        $details = $this->departmentService->getDepartmentDetails($dept);

        return view('admin.pages.departmentDetails', array_merge(['dept' => $dept], $details));
    }

    // --- FORM METHODS ---

    /*
     * Renders the unified form used for both creating and updating departments.
     */
    public function departmentForm(?Department $dept = null)
    {
        if ($dept?->trashed()) {
            return redirect()->route('admin.departments')->with('error', 'The department you are trying to edit has been archived by another administrator.');
        }

        Gate::authorize('departmentForm', $dept ?? Department::class);
        return view('admin.forms.departmentForm', compact('dept'));
    }

    // --- MUTATING METHODS ---

    /*
     * Processes validated request data to store a newly created department in the database.
     */
    public function store(StoreDepartmentRequest $request)
    {
        Gate::authorize('create', Department::class);

        Department::create($request->validated());

        return redirect()->route('admin.departments')->with('success', 'Department created successfully.');
    }

    /*
     * Processes validated request data to commit updates to an existing department.
     * Redirects back to the originating view (dashboard or details page) based on the query parameter.
     */
    public function update(UpdateDepartmentRequest $request, Department $dept)
    {
        if ($dept->trashed()) {
            return redirect()->route('admin.departments')->with('error', 'Failed to save changes. The department was recently archived by another administrator.');
        }

        Gate::authorize('update', $dept);

        $result = $this->departmentService->updateDepartment($dept, $request->validated());
        
        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message'])->withInput();
        }

        $redirectRoute = $request->query('source') === 'details' 
            ? route('admin.departments.show', $dept) 
            : route('admin.departments');

        if (!$result['changed']) {
            return redirect($redirectRoute)->with('info', 'No changes were made to the department.');
        }

        return redirect($redirectRoute)->with('success', 'Department updated successfully.');
    }

    // --- DESTRUCTIVE & STATE METHODS ---

    /*
     * Renders the confirmation prompt for archiving or permanently deleting a department.
     */
    public function deleteConfirm(Request $request, Department $dept)
    {
        $isForceDelete = $request->routeIs('admin.departments.forceDeleteConfirm');

        // State Guards: Prevent loading the prompt if the state already shifted
        if ($isForceDelete && !$dept->trashed()) {
            return redirect()->route('admin.departments')->with('error', 'This department was restored by another administrator and must be archived before permanent deletion.');
        }

        if (!$isForceDelete && $dept->trashed()) {
            return redirect()->route('admin.departments')->with('info', 'This department has already been archived.');
        }

        Gate::authorize('deleteConfirm', $dept);

        $title = $isForceDelete ? 'Permanently Delete Department' : 'Archive Department';
        return view('admin.prompts.departmentDeleteConfirm', compact('dept', 'title', 'isForceDelete'));
    }

    /*
     * Executes a soft delete to safely archive the specified department.
     */
    public function archive(Department $dept)
    {
        if ($dept->trashed()) {
            return redirect()->route('admin.departments')->with('error', 'This department has already been archived.');
        }

        Gate::authorize('archive', $dept);
        
        $result = $this->departmentService->archiveDepartment($dept);
        
        if (!$result['success']) {
            return redirect()->route('admin.departments')->with('error', $result['message']);
        }

        return redirect()->route('admin.departments')->with('success', $result['message']);
    }

    /*
     * Recovers a previously archived department back to active status.
     */
    public function restore($id) 
    {
        Gate::authorize('restore', Department::class);

        $result = $this->departmentService->restoreDepartment($id);

        if (!$result['success']) {
            return redirect()->route('admin.departments')->with('error', $result['message']);
        }

        return redirect()->route('admin.departments')->with('success', $result['message']);
    }

    /*
     * Permanently eradicates the department record from the database.
     * Delegates to the service layer to ensure no relational constraints are violated.
     */
    public function destroy($id)
    {
        Gate::authorize('forceDelete', Department::class);
        
        $result = $this->departmentService->permanentlyDeleteDepartment($id);

        if (!$result['success']) {
            return redirect()->route('admin.departments')->with('error', $result['message']);
        }
        
        return redirect()->route('admin.departments')->with('success', $result['message']);
    }
}