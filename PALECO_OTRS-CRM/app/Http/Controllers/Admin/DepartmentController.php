<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use App\Http\Requests\Admin\Department\StoreDepartmentRequest;
use App\Http\Requests\Admin\Department\UpdateDepartmentRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Department::class);

        $departments = Department::query()->withCount([
            'users as active_foremen_count' => function ($query) {
                $query->where('is_active', true)->whereHas('assignedRole', function ($q) {
                    $q->where('slug_identifier', 'foreman');
                });
            },
            'teams as active_team_count'
        ]);

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

    public function show(Request $request, Department $dept)
    {
        Gate::authorize('view', $dept);

        // Paginate Teams (using default 'page' query string)
        $assignedTeams = $dept->teams()->withCount('members')->paginate(5, ['*'], 'page');

        // Get total field personnel attached to teams in this department
        $personnelCount = User::query()->where('is_active', true)
            ->whereHas('assignedRole', function ($q) {
                $q->where('slug_identifier', 'field_personnel');
            })
            ->whereHas('teams', function ($query) use ($dept) {
                $query->where('department_id', $dept->id);
            })->count();

        // Paginate Foremen (using a custom 'page_foreman' query string to avoid conflicts)
        $foremanQuery = $dept->users()->where('is_active', true)->whereHas('assignedRole', function ($q) {
            $q->where('slug_identifier', 'foreman');
        });
        
        $foremanCount = $foremanQuery->count();
        $foremanCollection = $foremanQuery->paginate(5, ['*'], 'page_foreman');

        return view('admin.pages.departmentDetails', compact('assignedTeams', 'dept', 'foremanCount', 'foremanCollection', 'personnelCount'));
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