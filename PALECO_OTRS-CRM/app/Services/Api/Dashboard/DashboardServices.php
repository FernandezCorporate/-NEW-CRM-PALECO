<?php

namespace App\Services\Api\Dashboard;

use App\Models\User;
use App\Models\Ticket;

use App\Enums\TicketStatus;

class DashboardServices
{
// --- QUERY METHODS ---

    public function getForemanDashboardData(User $user): array
    {
        $baseQuery = Ticket::where('department_id', $user->department_id);

        // 1. Calculate KPI Counts
        $needsAssignmentCount = (clone $baseQuery)
            ->whereNull('team_id')
            ->where('status', TicketStatus::OPEN)
            ->count();

        $inProgressCount = (clone $baseQuery)->where('status', TicketStatus::IN_PROGRESS)->count();
        $pendingVerificationCount = (clone $baseQuery)->where('status', TicketStatus::RESOLVED)->count();
        $escalationReviewCount = (clone $baseQuery)->where('status', TicketStatus::PENDING_ESCALATION)->count();

        // 2. Fetch Accordion Previews (Max 5, Eager Loaded)
        $relations = ['category', 'team', 'creator'];

        return [
            'kpis' => [
                'total'            => (clone $baseQuery)->count(),
                'needs_assignment' => $needsAssignmentCount,
                'in_progress'      => $inProgressCount,
                'closed'           => (clone $baseQuery)->where('status', TicketStatus::CLOSED)->count(),
            ],
            'accordions' => [
                'needs_assignment' => [
                    'total_count' => $needsAssignmentCount,
                    'tickets'     => (clone $baseQuery)->with($relations)
                                        ->whereNull('team_id')
                                        ->where('status', TicketStatus::OPEN)
                                        ->latest('reported_at')->limit(5)->get(),
                ],
                'in_progress' => [
                    'total_count' => $inProgressCount,
                    'tickets'     => (clone $baseQuery)->with($relations)
                                        ->where('status', TicketStatus::IN_PROGRESS)
                                        ->latest('started_at')->limit(5)->get(),
                ],
                'pending_verification' => [
                    'total_count' => $pendingVerificationCount,
                    'tickets'     => (clone $baseQuery)->with($relations)
                                        ->where('status', TicketStatus::RESOLVED)
                                        ->latest('resolved_at')->limit(5)->get(),
                ],
                'escalation_review' => [
                    'total_count' => $escalationReviewCount,
                    'tickets'     => (clone $baseQuery)->with($relations)
                                        ->where('status', TicketStatus::PENDING_ESCALATION)
                                        ->latest('updated_at')->limit(5)->get(),
                ],
            ]
        ];
    }
}