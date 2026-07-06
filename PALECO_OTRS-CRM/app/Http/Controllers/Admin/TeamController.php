<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeamMemberRoles;
use App\Enums\UserRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Team\StoreTeamRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Team::class);

        // Fetch departments for the filter dropdown
        $departments = Department::whereNull('deleted_at')->orderBy('dept_name')->pluck('dept_name', 'id');

        // Start the base query with relationships
        $teams = Team::query()
            ->with('department')
            ->withCount('members');

        // Apply Search
        if ($request->filled('search')) {
            $teams = $teams->search($request->search);
        }

        // Apply Filter
        if ($request->filled('filter')) {
            $teams = $teams->filter($request->filter);
        }

        // Apply Sort OR Default to Newest
        if ($request->filled('sort')) {
            $teams = $teams->sort($request->sort);
        } else {
            $teams = $teams->latest(); // Default sorting when the page first loads
        }

        // Paginate
        $teams = $teams->paginate(9)->withQueryString();

        return view('admin.pages.teamManagement', compact('teams', 'departments'));
    }

    public function teamForm(?Team $team)
    {
        if ($team && $team->exists) {
            Gate::authorize('teamForm', [Team::class, $team]);
        } else {
            Gate::authorize('teamForm', Team::class);
        }

        $depts = Department::orderBy('dept_name')->pluck('dept_name', 'id');
        $personnel = User::query()
            ->where('role', UserRoles::FIELD_PERSONNEL)
            ->where('is_active', true)
            ->orderBy('first_name', 'asc')
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'name_ext'])
            ->get();
        $memberRoles = TeamMemberRoles::cases();

        return view('admin.forms.teamForm', compact('team', 'depts', 'personnel', 'memberRoles'));
    }

    public function store(StoreTeamRequest $request)
    {
        Gate::authorize('create', Team::class);

        // 1. Separate the base Team details from the dynamic members array
        $teamDetails = $request->safe()->except('members');
        $assignedMembers = $request->validated('members', []);

        // 2. Wrap the multi-table insertions in an ACID-compliant transaction
        DB::transaction(function () use ($teamDetails, $assignedMembers) {
            
            // Create the parent Team record
            $team = Team::create($teamDetails);

            // Format and sync the pivot data if members were added
            if (!empty($assignedMembers)) {
                $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                    return [
                        $member['user_id'] => ['team_role' => $member['team_role']]
                    ];
                });

                // sync() prevents duplicates and strictly mirrors the provided array
                $team->members()->sync($formattedMembers);
            }
        });

        return redirect()->route('admin.teams')->with('success', 'Team and members created successfully.');
    }
}