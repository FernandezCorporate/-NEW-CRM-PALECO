<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Services\Api\Dashboard\DashboardServices;
use App\Http\Resources\Api\ForemanDashboardResource;

class DashboardController extends Controller
{
    public function __construct(protected DashboardServices $dashboardServices) {}

    public function foremanIndex(Request $request): JsonResponse
    {
        // 1. Policy Gate
        Gate::authorize('viewForemanDashboard', User::class);

        // 2. Delegate to Service
        $data = $this->dashboardServices->getForemanDashboardData($request->user());

        // 3. Format Response via Resource
        return response()->json([
            'success' => true,
            'data'    => new ForemanDashboardResource($data)
        ]);
    }
}