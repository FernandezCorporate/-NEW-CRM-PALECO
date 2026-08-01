<?php

namespace App\Http\Controllers\Api\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Teams\StoreTeamRequest;
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
}