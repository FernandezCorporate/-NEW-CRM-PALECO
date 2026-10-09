<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SupervisorDashboardResource;
use App\Models\User;
use App\Services\Dashboard\ApiDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/*
 * Manages supervisor dashboard metric aggregation for the mobile API.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected ApiDashboardService $dashboardService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Fetches real-time dashboard KPIs and accordion ticket collections for the supervisor.
     */
    public function supervisorIndex(Request $request): JsonResponse
    {
        Gate::authorize('viewSupervisorDashboard', User::class);

        $data = $this->dashboardService->getSupervisorDashboardData($request->user());

        return response()->json([
            'success' => true,
            'data' => new SupervisorDashboardResource($data),
        ], Response::HTTP_OK);
    }
}
