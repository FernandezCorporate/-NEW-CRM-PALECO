<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Teams\ArchiveTeam;
use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\ForceDeleteTeam;
use App\Actions\Teams\RestoreTeam;
use App\Actions\Teams\UpdateTeam;
use App\Concerns\ResolvesSmartRedirects;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\StoreTeamRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Models\Team;
use App\Services\Teams\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/*
 * Manages the lifecycle and web interfaces for operational Teams.
 * Handles the creation, modification, member assignment, and archiving of teams.
 */
class TeamController extends Controller
{
    use ResolvesSmartRedirects;

    /*
     * Injects the TeamService to handle queries and form options.
     */
    public function __construct(protected TeamService $teamService) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves and renders the paginated list of teams for the management dashboard.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Team::class);

        $dashboardData = $this->teamService->getDashboardTeams($request->all());

        session()->put('team_list_url', $request->fullUrl());

        return view('admin.pages.teamManagement', $dashboardData);
    }

    /*
     * Retrieves and renders the detailed profile and assigned personnel of a specific team.
     */
    public function show(Team $team)
    {
        Gate::authorize('view', $team);

        $details = $this->teamService->getTeamDetails($team);

        return view('admin.pages.teamDetails', array_merge(['team' => $team], $details));
    }

    // --- FORM METHODS ---

    /*
     * Renders the unified form used for both creating and updating teams, alongside required selector data.
     */
    public function teamForm(?Team $team = null)
    {
        if ($team?->trashed()) {
            return redirect()->route('admin.teams')->with('error', 'The team you are trying to edit has been archived by another administrator.');
        }

        Gate::authorize('teamForm', $team ?? Team::class);

        $formData = $this->teamService->getFormData();

        return view('admin.forms.teamForm', array_merge(['team' => $team], $formData));
    }

    // --- MUTATING METHODS ---

    /*
     * Processes validated request data to store a new team and attach its specified members using CreateTeam action.
     */
    public function store(StoreTeamRequest $request, CreateTeam $createTeam)
    {
        Gate::authorize('create', Team::class);

        $createTeam->execute(
            $request->safe()->except('members'),
            $request->validated('members', [])
        );

        return redirect()->route('admin.teams')->with('success', 'Team and members created successfully.');
    }

    /*
     * Processes validated request data to commit updates to a team and sync its membership list using UpdateTeam action.
     */
    public function update(UpdateTeamRequest $request, Team $team, UpdateTeam $updateTeam)
    {
        if ($team->trashed()) {
            return redirect()->route('admin.teams')->with('error', 'Failed to save changes. The team was recently archived by another administrator.');
        }

        Gate::authorize('update', $team);

        $result = $updateTeam->execute(
            $team,
            $request->safe()->except('members'),
            $request->validated('members', [])
        );

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['message'])->withInput();
        }

        $redirectRoute = $this->resolveSmartRedirect($request, 'admin.teams', [
            'details' => function (Request $req) use ($team) {
                $userId = $req->input('user', $req->query('user'));
                $deptId = $req->input('dept', $req->query('dept'));
                return route('admin.teams.show', array_filter([
                    'team' => $team,
                    'source' => $userId ? 'user' : ($deptId ? 'department' : null),
                    'user' => $userId,
                    'dept' => $deptId,
                ]));
            },
            'user' => function (Request $req) use ($team) {
                $userId = $req->input('user', $req->query('user'));
                return $userId ? route('admin.users.show', ['user' => $userId]) : route('admin.teams.show', ['team' => $team]);
            },
            'department' => function (Request $req) use ($team) {
                $deptId = $req->input('dept', $req->query('dept', $team->department_id));
                return $deptId ? route('admin.departments.show', ['dept' => $deptId]) : route('admin.teams.show', ['team' => $team]);
            },
        ]);

        if (! $result['changed']) {
            return redirect($redirectRoute)->with('info', 'No changes were made to the team.');
        }

        return redirect($redirectRoute)->with('success', 'Team updated successfully.');
    }

    // --- DESTRUCTIVE & STATE METHODS ---

    /*
     * Renders the confirmation prompt for archiving or permanently deleting a team.
     */
    public function deleteConfirm(Request $request, Team $team)
    {
        $isForceDelete = $request->routeIs('admin.teams.forceDeleteConfirm');

        // State Guards
        if ($isForceDelete && ! $team->trashed()) {
            return redirect()->route('admin.teams')->with('error', 'This team was restored by another administrator and must be archived before permanent deletion.');
        }

        if (! $isForceDelete && $team->trashed()) {
            return redirect()->route('admin.teams')->with('info', 'This team has already been archived.');
        }

        Gate::authorize('deleteConfirm', clone $team);

        $title = $isForceDelete ? 'Permanently Delete Team' : 'Archive Team';

        return view('admin.prompts.teamDeleteConfirm', compact('team', 'title', 'isForceDelete'));
    }

    /*
     * Executes a soft delete to safely archive the specified team using ArchiveTeam action.
     */
    public function archive(Team $team, ArchiveTeam $archiveTeam)
    {
        if ($team->trashed()) {
            return redirect()->route('admin.teams')->with('error', 'This team has already been archived.');
        }

        Gate::authorize('archive', $team);

        $result = $archiveTeam->execute($team);

        if (! $result['success']) {
            return redirect()->route('admin.teams')->with('error', $result['message']);
        }

        return redirect()->route('admin.teams')->with('success', $result['message']);
    }

    /*
     * Recovers a previously archived team back to active status using RestoreTeam action.
     */
    public function restore(Team $team, RestoreTeam $restoreTeam)
    {
        Gate::authorize('restore', Team::class);

        $result = $restoreTeam->execute($team);

        if (! $result['success']) {
            return redirect()->route('admin.teams')->with('error', $result['message']);
        }

        return redirect()->route('admin.teams')->with('success', $result['message']);
    }

    /*
     * Permanently eradicates the team record from the database using ForceDeleteTeam action.
     */
    public function destroy(Team $team, ForceDeleteTeam $forceDeleteTeam)
    {
        Gate::authorize('forceDelete', Team::class);

        $result = $forceDeleteTeam->execute($team);

        if (! $result['success']) {
            return redirect()->route('admin.teams')->with('error', $result['message']);
        }

        return redirect()->route('admin.teams')->with('success', $result['message']);
    }
}
