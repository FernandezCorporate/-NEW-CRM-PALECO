<?php

namespace App\Services\Api\Dashboard;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

/*
 * Aggregates analytical KPIs and categorized accordion data for the Supervisor mobile dashboard.
 */
class DashboardService
{
    // --- QUERY & AGGREGATION METHODS ---

    /*
     * Compiles supervisor KPI metrics and ticket collections for dashboard accordions.
     */
    public function getSupervisorDashboardData(User $user): array
    {
        $baseQuery = Ticket::where('department_id', $user->department_id);
        $relations = ['category', 'team', 'creator'];

        // Map requirements for each accordion bucket
        $sections = [
            'needs_assignment' => ['status' => TicketStatus::OPEN, 'order' => 'reported_at', 'unassigned' => true],
            'in_progress' => ['status' => TicketStatus::IN_PROGRESS, 'order' => 'started_at'],
            'pending_verification' => ['status' => TicketStatus::RESOLVED, 'order' => 'resolved_at'],
            'endorsement_review' => ['status' => TicketStatus::PENDING_ENDORSEMENT, 'order' => 'updated_at'],
        ];

        $accordions = [];

        // Iterate through definitions to aggregate counts and eager-load latest previews
        foreach ($sections as $key => $config) {
            $query = clone $baseQuery;
            $query->where('status', $config['status']);

            if (! empty($config['unassigned'])) {
                $query->whereNull('team_id');
            }

            $accordions[$key] = [
                'total_count' => (clone $query)->count(),
                'tickets' => $query->with($relations)->latest($config['order'])->limit(5)->get(),
            ];
        }

        return [
            'kpis' => [
                'total' => (clone $baseQuery)->count(),
                'needs_assignment' => $accordions['needs_assignment']['total_count'],
                'in_progress' => $accordions['in_progress']['total_count'],
                'closed' => (clone $baseQuery)->where('status', TicketStatus::CLOSED)->count(),
            ],
            'accordions' => $accordions,
        ];
    }
}
