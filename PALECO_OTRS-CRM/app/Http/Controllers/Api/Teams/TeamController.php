<?php

namespace App\Http\Controllers\Api\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Teams\StoreTeamRequest;
use App\Http\Requests\Api\Teams\UpdateTeamRequest;
use App\Http\Resources\Api\TeamResource;
use App\Services\Api\Teams\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use App\Models\Team;

/*
 * Manages the retrieval and creation of team rosters for the mobile application.
 */
class TeamController extends Controller
{
    public function __construct(protected TeamService $teamService) {}

    /*
     * Fetches a list of teams belonging to the authenticated Foreman's department.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAnyDepartmentTeams', Team::class);

        $teams = $this->teamService->getDepartmentTeams(
            $request->user(),
            $request->only(['search', 'sort', 'filter']) 
        );

        return TeamResource::collection($teams);
    }

    /*
     * Fetches detailed information, workload statistics, and the roster for a specific team.
     */
    public function show(Request $request, Team $team)
    {
        $team = $this->teamService->getDepartmentTeam(
            $request->user(), 
            $team
        );

        return new TeamResource($team);
    }

    /*
     * Validates and processes the creation of a new team and syncs initial members.
     */
    public function store(StoreTeamRequest $request)
    {
        Gate::authorize('create', Team::class);

        // 1. Extract the core team data safely without the members array
        $teamDetails = $request->safe()->except('members');
        
        // 2. Securely inject the foreman's department directly from their auth token
        $teamDetails['department_id'] = $request->user()->department_id;

        // 3. Hand off to the service (this reuses the exact same logic as your web admin service)
        $this->teamService->createTeam(
            $teamDetails,
            $request->validated('members', [])
        );

        // 4. Return standard API created response
        return response()->json([
            'success' => true,
            'message' => 'Team and members created successfully.'
        ], 201);
    }

    public function update(UpdateTeamRequest $request, Team $team)
    {
        if ($team->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Conflict: This team has been archived by an administrator and can no longer be modified.'
            ], 409); // 409 Conflict or 403 Forbidden
        }
        // 1. Policy Authorization (Ensures the foreman owns the team's department)
        Gate::authorize('mobileUpdateTeam', $team);

        // 2. Extract safe core team data
        $teamDetails = $request->safe()->except('members');

        // 3. Force the department ID to match the foreman's secure token
        $teamDetails['department_id'] = $request->user()->department_id;

        // 4. Hand off to the Service
        $result = $this->teamService->updateTeam(
            $team,
            $teamDetails,
            $request->validated('members', [])
        );

        // 5. Handle Optimistic Locking Conflict (HTTP 409 Conflict)
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 409); 
        }

        // 6. Return standard API success response (HTTP 200 OK)
        return response()->json([
            'success' => true,
            'message' => $result['changed'] ? 'Team updated successfully.' : 'No changes were made to the team.'
        ], 200);
    }

    public function archive(Request $request, Team $team)
    {
        if ($team->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Conflict: This team has already been archived by an administrator.'
            ], 409); 
        }

        Gate::authorize('mobileArchiveTeam', $team);

        $result = $this->teamService->archiveTeam($team);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 409); 
        }

        return response()->json([
            'success' => true,
            'message' => $result['message']
        ], 200);
    }

    public function restore(Request $request, Team $team)
    {
        Gate::authorize('mobileRestoreTeam', $team);

        $result = $this->teamService->restoreTeam($team->id);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 409); 
        }

        return response()->json([
            'success' => true,
            'message' => $result['message']
        ], 200);
    }
}