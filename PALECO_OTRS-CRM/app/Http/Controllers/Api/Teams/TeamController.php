<?php

namespace App\Http\Controllers\Api\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\StoreTeamRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Http\Resources\Api\TeamResource;
use App\Models\Team;
use App\Services\Teams\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/*
 * Manages the retrieval, configuration, and lifecycle transitions of field teams for the mobile API.
 */
class TeamController extends Controller
{
    public function __construct(
        protected TeamService $teamService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Fetches a paginated list of teams belonging to the supervisor's department.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAnyDepartmentTeams', Team::class);

        $teams = $this->teamService->deptTeamList(
            $request->user(),
            $request->only(['search', 'sort', 'filter'])
        );

        return TeamResource::collection($teams)->additional([
            'success' => true,
        ]);
    }

    /*
     * Fetches detailed information, workload statistics, and member roster for a specific team.
     */
    public function show(Request $request, Team $team): JsonResponse
    {
        Gate::authorize('viewDepartmentTeams', $team);

        $teamDetails = $this->teamService->deptTeamDetails(
            $request->user(),
            $team
        );

        return response()->json([
            'success' => true,
            'data' => new TeamResource($teamDetails),
        ], Response::HTTP_OK);
    }

    /*
     * Retrieves available personnel and team roles to populate mobile creation forms.
     */
    public function formOptions(Request $request): JsonResponse
    {
        Gate::authorize('mobileTeamOptions', Team::class);

        $options = $this->teamService->getFormOptions();

        return response()->json([
            'success' => true,
            'data' => $options,
        ], Response::HTTP_OK);
    }

    // --- MUTATING METHODS ---

    /*
     * Validates and processes the creation of a new team and syncs initial members.
     */
    public function store(StoreTeamRequest $request): JsonResponse
    {
        Gate::authorize('create', Team::class);

        $teamDetails = $request->safe()->except('members');
        $teamDetails['department_id'] = $request->user()->department_id;

        $newTeam = $this->teamService->createTeam(
            $teamDetails,
            $request->validated('members', [])
        );

        return response()->json([
            'success' => true,
            'message' => 'Team and members created successfully.',
            'data' => new TeamResource($newTeam),
        ], Response::HTTP_CREATED);
    }

    /*
     * Updates team details and roster memberships with concurrency collision detection.
     */
    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        Gate::authorize('mobileUpdateTeam', $team);

        $teamDetails = $request->safe()->except('members');
        $teamDetails['department_id'] = $request->user()->department_id;

        $result = $this->teamService->updateTeam(
            $team,
            $teamDetails,
            $request->validated('members', [])
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'success' => true,
            'message' => $result['changed'] ? 'Team updated successfully.' : 'No changes were made to the team.',
            'data' => new TeamResource($result['team']),
        ], Response::HTTP_OK);
    }

    // --- DESTRUCTIVE & STATE METHODS ---

    /*
     * Soft-deletes (archives) a team from active dispatch rosters.
     */
    public function archive(Request $request, Team $team): JsonResponse
    {
        Gate::authorize('mobileArchiveTeam', $team);

        $result = $this->teamService->archiveTeam($team);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => new TeamResource($result['team']),
        ], Response::HTTP_OK);
    }

    /*
     * Restores an archived team back to active operations.
     */
    public function restore(Request $request, Team $team): JsonResponse
    {
        Gate::authorize('mobileRestoreTeam', $team);

        $result = $this->teamService->restoreTeam($team);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => new TeamResource($result['team']),
        ], Response::HTTP_OK);
    }

    /*
     * Permanently purges a trashed team from the database.
     */
    public function destroy(Request $request, Team $team): JsonResponse
    {
        Gate::authorize('mobileDestroyTeam', $team);

        $result = $this->teamService->forceDeleteTeam($team);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ], Response::HTTP_OK);
    }
}
