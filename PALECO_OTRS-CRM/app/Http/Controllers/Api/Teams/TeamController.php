<?php

namespace App\Http\Controllers\Api\Teams;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TeamResource;
use App\Services\Api\Teams\TeamService;
use Illuminate\Http\Request;

/*
 * Manages the retrieval of team rosters for the mobile application.
 */
class TeamController extends Controller
{
    public function __construct(protected TeamService $teamService) {}

    /*
     * Fetches a list of all teams belonging to the authenticated Foreman's department.
     */
    public function index(Request $request)
    {
        $teams = $this->teamService->getDepartmentTeams(
            $request->user(),
            $request->only(['search', 'sort'])
        );

        return TeamResource::collection($teams);
    }
}