<?php

namespace App\Services\Dashboard;

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketEndorsement;
use App\Models\User;
use Carbon\Carbon;

/**
 * Aggregates operational statistics, complaint trends, and administrative KPIs for the web dashboards.
 */
class WebDashboardService
{
    // --- QUERY & AGGREGATION METHODS ---

    /**
     * Compiles high-level ticket status distribution, operational snapshots, and 7-day trend metrics.
     * Uses aggregated SQL group-by queries to avoid high memory hydration and N-query loops.
     */
    public function ticketOverview(): array
    {
        $statusCounts = Ticket::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = collect(TicketStatus::cases())->map(fn (TicketStatus $status) => [
            'key' => $status->value,
            'label' => $status->label(),
            'total' => (int) ($statusCounts->get($status->value) ?? 0),
        ])->values()->all();

        $startDate = Carbon::today()->subDays(6)->startOfDay();
        $rawTrends = Ticket::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date_key, COUNT(*) as total')
            ->groupBy('date_key')
            ->pluck('total', 'date_key');

        $trend = collect(range(6, 0))->map(function (int $daysAgo) use ($rawTrends) {
            $date = Carbon::today()->subDays($daysAgo);
            $dateKey = $date->format('Y-m-d');

            return [
                'label' => $date->format('D'),
                'date' => $date->format('M j'),
                'total' => (int) ($rawTrends->get($dateKey) ?? 0),
            ];
        })->values()->all();

        return [
            'operations' => $this->operationsSnapshot(),
            'total' => collect($statuses)->sum('total'),
            'statuses' => $statuses,
            'trend' => $trend,
            'trend_max' => max(1, collect($trend)->max('total')),
        ];
    }

    /**
     * Computes today's operational throughput, department workloads, aging buckets, and queues.
     */
    public function operationsSnapshot(): array
    {
        $now = Carbon::now();
        $active = Ticket::query()->whereIn('status', ['open', 'assigned', 'in_progress']);
        $departmentCounts = (clone $active)->selectRaw('department_id, COUNT(*) as total')
            ->groupBy('department_id')->get();
        $departments = Department::withTrashed()->whereIn('id', $departmentCounts->pluck('department_id')->filter())
            ->get()->keyBy('id');
        $workload = $departmentCounts->map(function ($row) use ($departments) {
            $department = $departments->get($row->department_id);

            return [
                'label' => $department
                    ? $department->dept_name.($department->trashed() ? ' (archived)' : '')
                    : 'No department',
                'total' => (int) $row->total,
            ];
        })->sortByDesc('total')->values();

        return [
            'today' => $now->format('M j, Y'),
            'timezone' => config('app.timezone'),
            'received_today' => Ticket::query()->whereBetween('created_at', [$now->copy()->startOfDay(), $now])->count(),
            'closed_today' => Ticket::query()->whereBetween('closed_at', [$now->copy()->startOfDay(), $now])->count(),
            'active' => (clone $active)->count(),
            'without_team' => Ticket::query()->where('status', TicketStatus::OPEN)->count(),
            'pending_endorsements' => TicketEndorsement::query()->where('status', 'pending')->whereHas('ticket')->count(),
            'pending_reports' => TicketAccomplishment::query()->where('status', 'pending')->whereHas('ticket')->count(),
            'aging' => [
                ['label' => 'Less than 24 hours', 'total' => (clone $active)->where('created_at', '>', $now->copy()->subDay())->count()],
                ['label' => '1 to under 7 days', 'total' => (clone $active)->where('created_at', '<=', $now->copy()->subDay())->where('created_at', '>', $now->copy()->subDays(7))->count()],
                ['label' => '7 days or more', 'total' => (clone $active)->where('created_at', '<=', $now->copy()->subDays(7))->count()],
            ],
            'departments' => $workload->take(6)->all(),
            'other_departments' => $workload->skip(6)->sum('total'),
            'department_max' => max(1, $workload->max('total') ?? 0),
        ];
    }

    /**
     * Summarizes system-wide user counts, departments, teams, and total ticket volumes for administrators.
     */
    public function adminSummary(): array
    {
        return [
            'users_total' => User::query()->toBase()->count(),
            'users_active' => User::query()->where('is_active', true)->toBase()->count(),
            'departments_active' => Department::query()->count(),
            'departments_archived' => Department::onlyTrashed()->count(),
            'tickets_total' => Ticket::query()->count(),
            'teams_active' => Team::query()->count(),
        ];
    }
}
