<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use App\Http\Requests\Admin\Team\StoreTeamRequest;
use App\Http\Requests\Admin\Team\UpdateTeamRequest;

use App\Services\Admin\TeamService;

use App\Models\Team;

/*
 * Manages the lifecycle and web interfaces for operational Teams.
 * Handles the creation, modification, member assignment, and archiving of teams.
 */
class TeamController extends Controller
{
    /*
     * Injects the TeamService to handle complex member assignments and team logic.
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
        Gate::authorize('teamForm', $team ?? Team::class);
        
        $formData = $this->teamService->getFormData();

        return view('admin.forms.teamForm', array_merge(['team' => $team], $formData));
    }

    // --- MUTATING METHODS ---

    /*
     * Processes validated request data to store a new team and attach its specified members.
     */
    public function store(StoreTeamRequest $request)
    {
        Gate::authorize('create', Team::class);

        $this->teamService->createTeam(
            $request->safe()->except('members'),
            $request->validated('members', [])
        );

        return redirect()->route('admin.teams')->with('success', 'Team and members created successfully.');
    }

    /*
     * Processes validated request data to commit updates to a team and sync its membership list.
     * Redirects back to the originating view (dashboard or details page) if no changes were detected.
     */
    public function update(UpdateTeamRequest $request, Team $team)
    {
        Gate::authorize('update', $team);

        $result = $this->teamService->updateTeam(
            $team,
            $request->safe()->except('members'),
            $request->validated('members', [])
        );

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message'])->withInput();
        }

        $redirectRoute = $request->query('source') === 'details' 
            ? route('admin.teams.show', $team) 
            : route('admin.teams');

        if (!$result['changed']) {
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
        Gate::authorize('deleteConfirm', clone $team);

        $isForceDelete = $request->routeIs('admin.teams.forceDeleteConfirm');
        $title = $isForceDelete ? 'Permanently Delete Team' : 'Archive Team';

        return view('admin.prompts.teamDeleteConfirm', compact('team', 'title', 'isForceDelete'));
    }

    /*
     * Executes a soft delete to safely archive the specified team.
     */
    public function archive(Team $team)
    {
        Gate::authorize('archive', $team);
        
        $result = $this->teamService->archiveTeam($team);
        
        if (!$result['success']) {
            return redirect()->route('admin.teams')->with('error', $result['message']);
        }
        
        return redirect()->route('admin.teams')->with('success', $result['message']);
    }

    /*
     * Recovers a previously archived team back to active status.
     */
    public function restore(string $id) // FIXED: ULIDs are strings, not ints
    {
        Gate::authorize('restore', Team::class);

        $result = $this->teamService->restoreTeam($id);

        if (!$result['success']) {
            return redirect()->route('admin.teams')->with('error', $result['message']);
        }

        return redirect()->route('admin.teams')->with('success', $result['message']);
    }

    /*
     * Permanently eradicates the team record from the database, preventing orphaned tickets.
     */
    public function destroy(string $id) // FIXED: ULIDs are strings, not ints
    {
        Gate::authorize('forceDelete', Team::class);
        
        $result = $this->teamService->forceDeleteTeam($id);

        if (!$result['success']) {
            return redirect()->route('admin.teams')->with('error', $result['message']);
        }

        return redirect()->route('admin.teams')->with('success', $result['message']);
    }
}