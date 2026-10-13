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

        $statusMap = $statusCounts->all();

        $statuses = collect(TicketStatus::cases())->map(fn (TicketStatus $status) => [
            'key' => $status->value,
            'label' => $status->label(),
            'total' => (int) ($statusMap[$status->value] ?? 0),
            'chart_class' => $status->chartClass(),
            'dot_class' => $status->dotClass(),
            'color_hex' => $status->colorHex(),
            'badge_classes' => $status->badgeClasses(),
        ])->values()->all();

        $totalTickets = max(1, collect($statuses)->sum('total'));
        $statusesWithWidth = collect($statuses)->map(function ($status) use ($totalTickets) {
            $status['width_percent'] = round(($status['total'] / $totalTickets) * 100, 2);

            return $status;
        })->all();

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

        $trendMax = max(1, collect($trend)->max('total'));
        $trendSum = (int) collect($trend)->sum('total');

        $trendWithHeight = collect($trend)->map(function ($day) use ($trendMax) {
            $day['height_percent'] = round(max(4, ($day['total'] / $trendMax) * 100), 1);

            return $day;
        })->all();

        $operations = $this->operationsSnapshot();

        $workflowCards = [
            [
                'label' => 'Open tickets',
                'value' => (int) ($statusMap[TicketStatus::OPEN->value] ?? 0),
                'description' => 'Awaiting assignment or action',
                'url' => route('cwd.tickets', ['status' => 'open']),
                'tone' => 'bg-sky-50 text-sky-600',
                'icon' => 'ticket',
            ],
            [
                'label' => 'In progress',
                'value' => (int) ($statusMap[TicketStatus::IN_PROGRESS->value] ?? 0),
                'description' => 'Currently handled by field teams',
                'url' => route('cwd.tickets', ['status' => 'in_progress']),
                'tone' => 'bg-amber-50 text-amber-600',
                'icon' => 'clock',
            ],
            [
                'label' => 'Endorsements',
                'value' => (int) ($statusMap[TicketStatus::PENDING_ENDORSEMENT->value] ?? 0),
                'description' => 'Cases requiring added attention',
                'url' => route('cwd.endorsements'),
                'tone' => 'bg-orange-50 text-orange-600',
                'icon' => 'endorsement',
            ],
        ];

        $cwdControls = [
            ['tickets', 'Manage tickets', 'View all service requests', route('cwd.tickets'), 'bg-emerald-50 text-emerald-600', 'ticket'],
            ['new-ticket', 'New ticket', 'Record a service complaint', route('cwd.tickets.createForm', ['source' => 'dashboard']), 'bg-teal-50 text-teal-600', 'plus'],
            ['open-tickets', 'Open tickets', 'Review unassigned requests', route('cwd.tickets', ['status' => 'open']), 'bg-sky-50 text-sky-600', 'ticket'],
            ['in-progress', 'Work in progress', 'Follow ongoing field work', route('cwd.tickets', ['status' => 'in_progress']), 'bg-blue-50 text-blue-600', 'clock'],
            ['endorsements', 'Review endorsements', 'View all endorsement requests', route('cwd.endorsements'), 'bg-amber-50 text-amber-600', 'endorsement'],
            ['pending-endorsements', 'Pending reviews', 'Endorsements awaiting a decision', route('cwd.endorsements', ['status' => 'pending']), 'bg-orange-50 text-orange-600', 'exclamation-triangle'],
        ];

        $queueItems = [
            ['Open tickets', (int) ($statusMap[TicketStatus::OPEN->value] ?? 0), 'Review newly logged requests', route('cwd.tickets', ['status' => 'open'])],
            ['Pending endorsements', (int) ($operations['pending_endorsements'] ?? 0), 'Review requests for rerouting', route('cwd.endorsements', ['status' => 'pending'])],
            ['Assigned tickets', (int) ($statusMap[TicketStatus::ASSIGNED->value] ?? 0), 'Follow up with assigned field teams', route('cwd.tickets', ['status' => 'assigned'])],
        ];

        return [
            'operations' => $operations,
            'total' => collect($statuses)->sum('total'),
            'statuses' => $statusesWithWidth,
            'trend' => $trendWithHeight,
            'trend_sum' => $trendSum,
            'trend_max' => $trendMax,
            'workflow_cards' => $workflowCards,
            'cwd_controls' => $cwdControls,
            'queue_items' => $queueItems,
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
        $usersTotal = User::query()->toBase()->count();
        $usersActive = User::query()->where('is_active', true)->toBase()->count();
        $deptsActive = Department::query()->count();
        $deptsArchived = Department::onlyTrashed()->count();
        $ticketsTotal = Ticket::query()->count();
        $teamsActive = Team::query()->count();

        $metrics = [
            ['Users', $usersTotal, 'Total accounts', route('admin.users'), 'bg-emerald-50 text-emerald-600', 'bg-emerald-500', 'users'],
            ['Active users', $usersActive, 'Enabled accounts', route('admin.users'), 'bg-teal-50 text-teal-600', 'bg-teal-500', 'check-circle'],
            ['Active departments', $deptsActive, 'Current divisions', route('admin.departments'), 'bg-sky-50 text-sky-600', 'bg-sky-500', 'department'],
            ['Archived departments', $deptsArchived, 'Retired divisions', route('admin.departments', ['filter' => 'archived']), 'bg-amber-50 text-amber-600', 'bg-amber-500', 'trash'],
            ['Total tickets', $ticketsTotal, 'All service requests', route('admin.monitoring.index'), 'bg-blue-50 text-blue-600', 'bg-blue-600', 'ticket'],
            ['Active teams', $teamsActive, 'Operational crews', route('admin.teams'), 'bg-violet-50 text-violet-600', 'bg-violet-500', 'team'],
        ];

        $metricMax = max(1, ...array_column($metrics, 1));
        $metricsWithWidth = collect($metrics)->map(function ($item) use ($metricMax) {
            $item[7] = round(($item[1] / $metricMax) * 100, 2); // width_percent

            return $item;
        })->all();

        $adminControls = [
            ['add-user', 'Add new user', 'Create a staff account', route('admin.users.createForm'), 'bg-emerald-50 text-emerald-600', 'plus'],
            ['users', 'Manage users', 'Review staff accounts', route('admin.users'), 'bg-teal-50 text-teal-600', 'users'],
            ['departments', 'Departments', 'Maintain service divisions', route('admin.departments'), 'bg-sky-50 text-sky-600', 'department'],
            ['teams', 'Field teams', 'Coordinate operational crews', route('admin.teams'), 'bg-violet-50 text-violet-600', 'team'],
            ['categories', 'Ticket categories', 'Organize complaint types', route('admin.ticketCategories'), 'bg-amber-50 text-amber-600', 'category'],
            ['monitoring', 'System monitoring', 'Review system activity', route('admin.monitoring.index'), 'bg-blue-50 text-blue-600', 'monitoring'],
        ];

        return [
            'users_total' => $usersTotal,
            'users_active' => $usersActive,
            'departments_active' => $deptsActive,
            'departments_archived' => $deptsArchived,
            'tickets_total' => $ticketsTotal,
            'teams_active' => $teamsActive,
            'system_metrics' => $metricsWithWidth,
            'metric_max' => $metricMax,
            'admin_controls' => $adminControls,
        ];
    }
}
