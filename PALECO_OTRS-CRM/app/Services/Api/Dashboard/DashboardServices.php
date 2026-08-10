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
        $relations = ['category', 'team', 'creator'];

        // 1. Map the specific requirements for each accordion dynamically
        $sections = [
            'needs_assignment'     => ['status' => TicketStatus::OPEN, 'order' => 'reported_at', 'unassigned' => true],
            'in_progress'          => ['status' => TicketStatus::IN_PROGRESS, 'order' => 'started_at'],
            'pending_verification' => ['status' => TicketStatus::RESOLVED, 'order' => 'resolved_at'],
            'escalation_review'    => ['status' => TicketStatus::PENDING_ESCALATION, 'order' => 'updated_at'],
        ];

        $accordions = [];

        // 2. Iterate through the map to build queries, counts, and fetch previews
        foreach ($sections as $key => $config) {
            $query = clone $baseQuery;
            $query->where('status', $config['status']);
            
            if (!empty($config['unassigned'])) {
                $query->whereNull('team_id');
            }

            $accordions[$key] = [
                'total_count' => (clone $query)->count(),
                'tickets'     => $query->with($relations)->latest($config['order'])->limit(5)->get(),
            ];
        }

        // 3. Extract the needed KPIs and return the finalized payload
        return [
            'kpis' => [
                'total'            => (clone $baseQuery)->count(),
                'needs_assignment' => $accordions['needs_assignment']['total_count'],
                'in_progress'      => $accordions['in_progress']['total_count'],
                'closed'           => (clone $baseQuery)->where('status', TicketStatus::CLOSED)->count(),
            ],
            'accordions' => $accordions
        ];
    }
}