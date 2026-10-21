<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Departments\ArchiveDepartment;
use App\Actions\Departments\CreateDepartment;
use App\Actions\Departments\ForceDeleteDepartment;
use App\Actions\Departments\RestoreDepartment;
use App\Actions\Departments\UpdateDepartment;
use App\Concerns\ResolvesSmartRedirects;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\Departments\DepartmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/*
 * Manages the lifecycle and web interfaces for system Departments.
 * Handles viewing, creating, modifying, and archiving department records.
 */
class DepartmentController extends Controller
{
    use ResolvesSmartRedirects;

    /*
     * Injects the DepartmentService to handle queries and complex view state.
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
     * Processes validated request data to store a newly created department using CreateDepartment action.
     */
    public function store(StoreDepartmentRequest $request, CreateDepartment $createDepartment)
    {
        Gate::authorize('create', Department::class);

        $createDepartment->execute($request->validated());

        return redirect()->route('admin.departments')->with('success', 'Department created successfully.');
    }

    /*
     * Processes validated request data to commit updates to an existing department using UpdateDepartment action.
     */
    public function update(UpdateDepartmentRequest $request, Department $dept, UpdateDepartment $updateDepartment)
    {
        if ($dept->trashed()) {
            return redirect()->route('admin.departments')->with('error', 'Failed to save changes. The department was recently archived by another administrator.');
        }

        Gate::authorize('update', $dept);

        $result = $updateDepartment->execute($dept, $request->validated());

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['message'])->withInput();
        }

        $redirectRoute = $this->resolveSmartRedirect($request, 'admin.departments', [
            'details' => function (Request $req) use ($dept) {
                $userId = $req->input('user', $req->query('user'));
                $teamId = $req->input('team', $req->query('team'));

                return route('admin.departments.show', array_filter([
                    'dept' => $dept,
                    'source' => $userId ? 'user' : ($teamId ? 'team' : null),
                    'user' => $userId,
                    'team' => $teamId,
                ]));
            },
            'user' => function (Request $req) use ($dept) {
                $userId = $req->input('user', $req->query('user'));

                return $userId ? route('admin.users.show', ['user' => $userId]) : route('admin.departments.show', ['dept' => $dept]);
            },
            'team' => function (Request $req) use ($dept) {
                $teamId = $req->input('team', $req->query('team'));

                return $teamId ? route('admin.teams.show', ['team' => $teamId]) : route('admin.departments.show', ['dept' => $dept]);
            },
        ]);

        if (! $result['changed']) {
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
        if ($isForceDelete && ! $dept->trashed()) {
            return redirect()->route('admin.departments')->with('error', 'This department was restored by another administrator and must be archived before permanent deletion.');
        }

        if (! $isForceDelete && $dept->trashed()) {
            return redirect()->route('admin.departments')->with('info', 'This department has already been archived.');
        }

        Gate::authorize('deleteConfirm', $dept);

        $title = $isForceDelete ? 'Permanently Delete Department' : 'Archive Department';

        return view('admin.prompts.departmentDeleteConfirm', compact('dept', 'title', 'isForceDelete'));
    }

    /*
     * Executes a soft delete to safely archive the specified department using ArchiveDepartment action.
     */
    public function archive(Department $dept, ArchiveDepartment $archiveDepartment)
    {
        if ($dept->trashed()) {
            return redirect()->route('admin.departments')->with('error', 'This department has already been archived.');
        }

        Gate::authorize('archive', $dept);

        $result = $archiveDepartment->execute($dept);

        if (! $result['success']) {
            return redirect()->route('admin.departments')->with('error', $result['message']);
        }

        return redirect()->route('admin.departments')->with('success', $result['message']);
    }

    /*
     * Recovers a previously archived department back to active status using RestoreDepartment action.
     */
    public function restore($id, RestoreDepartment $restoreDepartment)
    {
        Gate::authorize('restore', Department::class);

        $result = $restoreDepartment->execute($id);

        if (! $result['success']) {
            return redirect()->route('admin.departments')->with('error', $result['message']);
        }

        return redirect()->route('admin.departments')->with('success', $result['message']);
    }

    /*
     * Permanently eradicates the department record from the database using ForceDeleteDepartment action.
     */
    public function destroy($id, ForceDeleteDepartment $forceDeleteDepartment)
    {
        Gate::authorize('forceDelete', Department::class);

        $result = $forceDeleteDepartment->execute($id);

        if (! $result['success']) {
            return redirect()->route('admin.departments')->with('error', $result['message']);
        }

        return redirect()->route('admin.departments')->with('success', $result['message']);
    }
}
