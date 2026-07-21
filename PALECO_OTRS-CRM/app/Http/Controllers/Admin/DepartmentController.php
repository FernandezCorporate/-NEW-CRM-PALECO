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
        Gate::authorize('update', $dept);

        $wasUpdated = $this->departmentService->updateDepartment($dept, $request->validated());
        
        $redirectRoute = $request->query('source') === 'details' 
            ? route('admin.departments.show', $dept) 
            : route('admin.departments');

        if (!$wasUpdated) {
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
        $dept = $isForceDelete ? Department::onlyTrashed()->findOrFail($dept->id) : $dept;
        Gate::authorize('deleteConfirm', $dept);

        $title = $isForceDelete ? 'Permanently Delete Department' : 'Archive Department';
        return view('admin.prompts.departmentDeleteConfirm', compact('dept', 'title', 'isForceDelete'));
    }

    /*
     * Executes a soft delete to safely archive the specified department.
     */
    public function archive(Department $dept)
    {
        Gate::authorize('archive', $dept);
        $dept->delete();
        return redirect()->route('admin.departments')->with('success', 'Department archived successfully.');
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