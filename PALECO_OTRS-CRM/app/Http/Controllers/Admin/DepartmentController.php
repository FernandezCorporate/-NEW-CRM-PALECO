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
            $departments = $departments->sort($request->input('sort'));
        } else {
            $departments = $departments->latest();
        }

        $departments = $departments->paginate(9)->withQueryString();

        return view('admin.pages.departmentManagement', compact('departments'));
    }

    public function show(Department $dept)
    {
        Gate::authorize('viewAny', $dept);

        return view('admin.pages.departmentDetails', compact('dept'));
    }

    public function departmentForm(?Department $dept = null)
    {
        Gate::authorize('departmentForm', Department::class);

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

        // Determine where to send them based on the clean URL flag
        $redirectRoute = $request->query('source') === 'details' 
            ? route('admin.departments.show', $dept) 
            : route('admin.departments');

        if ($dept->isClean()) {
            return redirect($redirectRoute)->with('info', 'No changes were made to the department.');
        }

        $dept->save();

        return redirect($redirectRoute)->with('success', 'Department updated successfully.');
    }

    public function deleteConfirm(Department $dept)
    {
        Gate::authorize('deleteConfirm', $dept);

        return view('admin.pages.departmentDeleteConfirm', compact('dept'));
    }

    public function archive(Department $dept)
    {
        Gate::authorize('archive', $dept);

        $dept->delete();

        // Always return to the index page because the detail page is now inaccessible
        return redirect()->route('admin.departments')->with('success', 'Department archived successfully.');
    }
}