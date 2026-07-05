<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Team::class);

        // Fetch departments for the filter dropdown
        $departments = Department::orderBy('dept_name')->pluck('dept_name', 'id');

        // Fetch teams with relationships, scopes, and pagination
        $teams = Team::query()
            ->with('department')
            ->withCount('members')
            ->search($request->search)
            ->filter($request->filter)
            ->sort($request->sort)
            ->paginate(9)
            ->withQueryString();

        return view('admin.pages.teamManagement', compact('teams', 'departments'));
    }
}