<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use App\Http\Requests\Admin\Department\StoreDepartmentRequest;
use App\Http\Requests\Admin\Department\UpdateDepartmentRequest;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Department::class);

        $departments = Department::query();

        if ($request->filled('search')) {
            $departments = $departments->search($request->search);
        }

        if ($request->filled('sort')) {
            $departments = $departments->sort($request->sort);
        } else {
            $departments = $departments->latest();
        }

        if ($request->input('filter') === 'archived') {
            $departments = $departments->onlyTrashed();
        }

        session()->put('department_list_url', $request->fullUrl());

        $departments = $departments->paginate(9)->withQueryString();

        return view('admin.pages.departmentManagement', compact('departments'));
    }

    public function show(Department $dept)
    {
        Gate::authorize('view', $dept);

        return view('admin.pages.departmentDetails', compact('dept'));
    }

    public function departmentForm(?Department $dept = null)
    {
        Gate::authorize('departmentForm', $dept ?? Department::class);

        return view('admin.forms.departmentForm', compact('dept'));
    }

    public function store(StoreDepartmentRequest $request)
    {
        Gate::authorize('create', Department::class);

        $validatedData = $request->validated();

        Department::create($validatedData);

        return redirect()->route('admin.departments')->with('success', 'Department created successfully.');
    }

    public function update(UpdateDepartmentRequest $request, Department $dept)
    {
        Gate::authorize('update', $dept);

        $dept->fill($request->validated());

        $redirectRoute = $request->query('source') === 'details' 
            ? route('admin.departments.show', $dept) 
            : route('admin.departments');

        if ($dept->isClean()) {
            return redirect($redirectRoute)->with('info', 'No changes were made to the department.');
        }

        $dept->save();

        return redirect($redirectRoute)->with('success', 'Department updated successfully.');
    }

    public function deleteConfirm(Request $request, Department $dept)
    {
        $isForceDelete = $request->routeIs('admin.departments.forceDeleteConfirm');

        // Fetch the trashed instance FIRST if it's a force delete
        $dept = $isForceDelete ? Department::onlyTrashed()->findOrFail($dept->id) : $dept;

        // Route to the specific policy method based on the action
        if ($isForceDelete) {
            Gate::authorize('forceDelete', $dept);
        } else {
            Gate::authorize('deleteConfirm', $dept);
        }

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
        $dept = Department::onlyTrashed()->findOrFail($id);

        Gate::authorize('restore', $dept);

        $nameExists = Department::where('dept_name', $dept->dept_name)->exists();

        if ($nameExists) {
            return redirect()->route('admin.departments')->with('error', 'Cannot restore department. An active department with the same name already exists.');
        }

        $dept->restore();

        return redirect()->route('admin.departments')->with('success', 'Department restored successfully.');
    }

    public function destroy($id)
    {
        $dept = Department::onlyTrashed()->findOrFail($id);

        Gate::authorize('forceDelete', $dept);

        $dept->forceDelete();

        return redirect()->route('admin.departments')->with('success', 'Department permanently deleted.');
    }
}