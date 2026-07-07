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

    public function show(Team $team)
    {
        Gate::authorize('view', $team);

        
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
            $oldMemberIds = $team->members()->pluck('users.id')->toArray();

            $team->update($teamDetails);

            $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                return [
                    $member['user_id'] => ['team_role' => $member['team_role']]
                ];
            });

            $changes = $team->members()->sync($formattedMembers);
            $newMemberIds = array_keys($formattedMembers->toArray());

            if (!empty($changes['attached']) || !empty($changes['detached']) || !empty($changes['updated'])) {
                activity()
                    ->useLog('Users')
                    ->performedOn($team)
                    ->event('roster_updated')
                    ->withProperties([
                        'old' => ['member_ids' => $oldMemberIds],
                        'attributes' => ['member_ids' => $newMemberIds]
                    ])
                    ->log("{$team->team_name} roster has been modified");
            }
        });

        return redirect()->route('admin.teams')->with('success', 'Team updated successfully.');
    }

    public function deleteConfirm(Request $request, Team $team)
    {
        Gate::authorize('deleteConfirm', clone $team);

        $isForceDelete = $request->routeIs('admin.teams.forceDeleteConfirm');
        $title = $isForceDelete ? 'Permanently Delete Team' : 'Archive Team';

        return view('admin.prompts.teamDeleteConfirm', compact('team', 'title', 'isForceDelete'));
    }

    public function archive(Team $team)
    {
        Gate::authorize('archive', $team);

        $team->delete();

        return redirect()->route('admin.teams')->with('success', 'Team archived successfully.');
    }

    public function restore($id)
    {
        // Using manual fetch because the current patch route in web.php doesn't use withTrashed()
        $team = Team::onlyTrashed()->findOrFail($id); 

        Gate::authorize('restore', clone $team);

        $nameExists = Team::where('team_name', $team->team_name)->exists();

        if ($nameExists) {
            return redirect()->route('admin.teams')->with('error', 'Cannot restore team. An active team with the same name already exists.');
        }

        $team->restore();

        return redirect()->route('admin.teams')->with('success', 'Team restored successfully.');
    }

    public function destroy($id)
    {
        // Using manual fetch because the current destroy route might need explicit binding validation
        $team = Team::onlyTrashed()->findOrFail($id);

        Gate::authorize('forceDelete', clone $team);
        
        $team->forceDelete();

        return redirect()->route('admin.teams')->with('success', 'Team permanently deleted.');
    }
}