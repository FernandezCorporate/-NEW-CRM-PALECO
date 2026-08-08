<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Services\Api\Dashboard\DashboardServices;
use App\Http\Resources\Api\TicketResource;

class DashboardController extends Controller
{
    public function __construct(protected DashboardServices $dashboardServices) {}

    public function foremanIndex(Request $request): JsonResponse
    {
        // 1. Policy Gate
        Gate::authorize('viewForemanDashboard', User::class);

        // 2. Delegate to Service
        $data = $this->dashboardServices->getForemanDashboardData($request->user());

        // 3. Format Response
        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => $data['kpis'],
                'accordions' => [
                    'needs_assignment' => [
                        'total_count' => $data['accordions']['needs_assignment']['total_count'],
                        'tickets'     => TicketResource::collection($data['accordions']['needs_assignment']['tickets']),
                    ],
                    'in_progress' => [
                        'total_count' => $data['accordions']['in_progress']['total_count'],
                        'tickets'     => TicketResource::collection($data['accordions']['in_progress']['tickets']),
                    ],
                    'pending_verification' => [
                        'total_count' => $data['accordions']['pending_verification']['total_count'],
                        'tickets'     => TicketResource::collection($data['accordions']['pending_verification']['tickets']),
                    ],
                    'escalation_review' => [
                        'total_count' => $data['accordions']['escalation_review']['total_count'],
                        'tickets'     => TicketResource::collection($data['accordions']['escalation_review']['tickets']),
                    ],
                ]
            ]
        ]);
    }
}