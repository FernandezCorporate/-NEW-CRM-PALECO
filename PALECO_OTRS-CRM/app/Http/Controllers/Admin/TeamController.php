<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeamMemberRoles;
use App\Enums\UserRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Team\StoreTeamRequest;
use App\Http\Requests\Admin\Team\UpdateTeamRequest;
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

        $departments = Department::whereNull('deleted_at')->orderBy('dept_name')->pluck('dept_name', 'id');

        $teams = Team::query()
            ->with('department')
            ->withCount('members');

        if ($request->filled('search')) {
            $teams = $teams->search($request->search);
        }

        if ($request->filled('filter')) {
            $teams = $teams->filter($request->filter);
        }

        // Handle Active vs Archived filtering
        if ($request->input('status') === 'archived') {
            $teams = $teams->onlyTrashed();
        }

        if ($request->filled('sort')) {
            $teams = $teams->sort($request->sort);
        } else {
            $teams = $teams->latest(); 
        }

        $teams = $teams->paginate(9)->withQueryString();

        return view('admin.pages.teamManagement', compact('teams', 'departments'));
    }

    public function teamForm(?Team $team = null)
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

        $teamDetails = $request->safe()->except('members');
        $assignedMembers = $request->validated('members', []);

        DB::transaction(function () use ($teamDetails, $assignedMembers) {
            $team = Team::create($teamDetails);

            if (!empty($assignedMembers)) {
                $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                    return [
                        $member['user_id'] => ['team_role' => $member['team_role']]
                    ];
                });
                $team->members()->sync($formattedMembers);
            }
        });

        return redirect()->route('admin.teams')->with('success', 'Team and members created successfully.');
    }

    public function update(UpdateTeamRequest $request, Team $team)
    {
        Gate::authorize('update', $team);

        $teamDetails = $request->safe()->except('members');
        $assignedMembers = $request->validated('members', []);

        DB::transaction(function () use ($team, $teamDetails, $assignedMembers) {
            $team->update($teamDetails);

            $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                return [
                    $member['user_id'] => ['team_role' => $member['team_role']]
                ];
            });

            $changes = $team->members()->sync($formattedMembers);

            $pivotChanged = count($changes['attached']) > 0 || count($changes['detached']) > 0 || count($changes['updated']) > 0;

            // 5. If the roster changed, manually write an activity log
            if ($pivotChanged) {
                activity()
                    ->useLog('Users') // Matches your Model's log name
                    ->performedOn($team)
                    ->event('updated')
                    ->log("{$team->team_name} (team) roster has been modified");
            }
        });

        return redirect()->route('admin.teams')->with('success', 'Team updated successfully.');
    }
}