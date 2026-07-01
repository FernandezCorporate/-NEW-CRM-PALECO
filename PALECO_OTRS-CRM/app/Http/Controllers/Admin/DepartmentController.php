<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use App\Http\Requests\Admin\Department\StoreDepartmentRequest;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Department::class);

        $departments = Department::query();

        if ($searchTerm = request('search')) {
            $departments = $departments->search($searchTerm);
        }

        if ($sortOption = request('sort')) {
            $departments = $departments->sort($sortOption);
        } else {
            $departments = $departments->latest();
        }

        $departments = $departments->paginate(9)
                                ->withQueryString();

        return view('admin.pages.departmentManagement', compact('departments'));
    }


    public function createForm()
    {
        Gate::authorize('createForm', Department::class);

        return view('admin.pages.departmentForm');
    }

    public function store(StoreDepartmentRequest $request)
    {
        Gate::authorize('create', Department::class);

        $validatedData = $request->validated();

        Department::create($validatedData);

        return redirect()->route('admin.departments')->with('success', 'Department created successfully.');
    }
}
