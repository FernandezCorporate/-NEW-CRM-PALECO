<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Team\StoreTeamRequest;
use App\Http\Requests\Admin\Team\UpdateTeamRequest;
use App\Services\Admin\TeamService;
use Illuminate\Http\Request;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    public function __construct(protected TeamService $teamService) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Team::class);

        $dashboardData = $this->teamService->getDashboardTeams($request->all());

        return view('admin.pages.teamManagement', $dashboardData);
    }

    public function show(Team $team)
    {
        Gate::authorize('view', $team);

        $details = $this->teamService->getTeamDetails($team);

        return view('admin.pages.teamDetails', array_merge(['team' => $team], $details));
    }

    public function teamForm(?Team $team = null)
    {
        Gate::authorize('teamForm', $team ?? Team::class);
        
        $formData = $this->teamService->getFormData();

        return view('admin.forms.teamForm', array_merge(['team' => $team], $formData));
    }

    public function store(StoreTeamRequest $request)
    {
        Gate::authorize('create', Team::class);

        $this->teamService->createTeam(
            $request->safe()->except('members'),
            $request->validated('members', [])
        );

        return redirect()->route('admin.teams')->with('success', 'Team and members created successfully.');
    }

    public function update(UpdateTeamRequest $request, Team $team)
    {
        Gate::authorize('update', $team);

        $this->teamService->updateTeam(
            $team,
            $request->safe()->except('members'),
            $request->validated('members', [])
        );

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
        Gate::authorize('restore', Team::class);

        $result = $this->teamService->restoreTeam($id);

        if (!$result['success']) {
            return redirect()->route('admin.teams')->with('error', $result['message']);
        }

        return redirect()->route('admin.teams')->with('success', $result['message']);
    }

    public function destroy($id)
    {
        Gate::authorize('forceDelete', Team::class);
        
        Team::onlyTrashed()->findOrFail($id)->forceDelete();

        return redirect()->route('admin.teams')->with('success', 'Team permanently deleted.');
    }
}