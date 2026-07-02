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


    public function departmentForm(?Department $dept = null)
    {
        Gate::authorize('departmentForm', Department::class);

        return view('admin.pages.departmentForm', compact('dept'));
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

        if ($dept->isClean()) {
            return redirect()->route('admin.departments')->with('info', 'No changes were made to the department.');
        }

        $dept->save();

        return redirect()->route('admin.departments')->with('success', 'Department updated successfully.');
    }
}
