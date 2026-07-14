<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use App\Http\Requests\Admin\Department\StoreDepartmentRequest;
use App\Http\Requests\Admin\Department\UpdateDepartmentRequest;
use App\Services\Admin\DepartmentService;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function __construct(protected DepartmentService $departmentService) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Department::class);

        $departments = $this->departmentService->getDashboardDepartments($request->all());
        
        session()->put('department_list_url', $request->fullUrl());

        return view('admin.pages.departmentManagement', compact('departments'));
    }

    public function show(Request $request, Department $dept)
    {
        Gate::authorize('view', $dept);

        $details = $this->departmentService->getDepartmentDetails($dept);

        return view('admin.pages.departmentDetails', array_merge(['dept' => $dept], $details));
    }

    public function departmentForm(?Department $dept = null)
    {
        Gate::authorize('departmentForm', $dept ?? Department::class);
        return view('admin.forms.departmentForm', compact('dept'));
    }

    public function store(StoreDepartmentRequest $request)
    {
        Gate::authorize('create', Department::class);

        Department::create($request->validated());

        return redirect()->route('admin.departments')->with('success', 'Department created successfully.');
    }

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

    public function deleteConfirm(Request $request, Department $dept)
    {
        $isForceDelete = $request->routeIs('admin.departments.forceDeleteConfirm');
        $dept = $isForceDelete ? Department::onlyTrashed()->findOrFail($dept->id) : $dept;
        Gate::authorize('deleteConfirm', $dept);

        $title = $isForceDelete ? 'Permanently Delete Department' : 'Archive Department';
        return view('admin.prompts.departmentDeleteConfirm', compact('dept', 'title', 'isForceDelete'));
    }

    public function archive(Department $dept)
    {
        Gate::authorize('archive', $dept);
        $dept->delete();
        return redirect()->route('admin.departments')->with('success', 'Department archived successfully.');
    }

    public function restore($id) 
    {
        Gate::authorize('restore', Department::class);

        $result = $this->departmentService->restoreDepartment($id);

        if (!$result['success']) {
            return redirect()->route('admin.departments')->with('error', $result['message']);
        }

        return redirect()->route('admin.departments')->with('success', $result['message']);
    }

    public function destroy($id)
    {
        Gate::authorize('forceDelete', Department::class);
        
        Department::onlyTrashed()->findOrFail($id)->forceDelete();
        
        return redirect()->route('admin.departments')->with('success', 'Department permanently deleted.');
    }
}