<?php

namespace App\Services\Dashboard;

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketCategory;
use App\Models\TicketEndorsement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

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

        $actualTotal = collect($statuses)->sum('total');
        $totalTickets = max(1, $actualTotal);

        $accumulatedPercent = 0.0;
        $statusesWithGeometry = collect($statuses)->map(function ($status) use (&$accumulatedPercent, $totalTickets, $actualTotal) {
            $slicePercent = ($status['total'] / $totalTickets) * 100;
            $status['width_percent'] = round($slicePercent, 1);
            $status['filter_url'] = route('cwd.tickets', ['status' => $status['key']]);

            if ($actualTotal === 0 || $status['total'] === 0) {
                $status['path_d'] = '';
                $status['start_percent'] = 0;
                $status['end_percent'] = 0;

                return $status;
            }

            $startPercent = $accumulatedPercent;
            $endPercent = $startPercent + $slicePercent;
            $accumulatedPercent = $endPercent;

            $status['start_percent'] = round($startPercent, 2);
            $status['end_percent'] = round($endPercent, 2);

            $cx = 100;
            $cy = 100;
            $outerR = 86;
            $innerR = 50;

            if ($slicePercent >= 99.99) {
                $status['path_d'] = sprintf(
                    'M %s %s A %s %s 0 1 1 %s %s A %s %s 0 1 1 %s %s M %s %s A %s %s 0 1 0 %s %s A %s %s 0 1 0 %s %s Z',
                    $cx, $cy - $outerR, $outerR, $outerR, $cx, $cy + $outerR, $outerR, $outerR, $cx, $cy - $outerR,
                    $cx, $cy - $innerR, $innerR, $innerR, $cx, $cy + $innerR, $innerR, $innerR, $cx, $cy - $innerR
                );
            } else {
                $startAngle = ($startPercent / 100) * 2 * M_PI;
                $endAngle = ($endPercent / 100) * 2 * M_PI;

                $x1 = round($cx + $outerR * sin($startAngle), 3);
                $y1 = round($cy - $outerR * cos($startAngle), 3);
                $x2 = round($cx + $outerR * sin($endAngle), 3);
                $y2 = round($cy - $outerR * cos($endAngle), 3);

                $x3 = round($cx + $innerR * sin($endAngle), 3);
                $y3 = round($cy - $innerR * cos($endAngle), 3);
                $x4 = round($cx + $innerR * sin($startAngle), 3);
                $y4 = round($cy - $innerR * cos($startAngle), 3);

                $largeArc = ($endAngle - $startAngle) > M_PI ? 1 : 0;

                $status['path_d'] = sprintf(
                    'M %s %s A %s %s 0 %d 1 %s %s L %s %s A %s %s 0 %d 0 %s %s Z',
                    $x1, $y1, $outerR, $outerR, $largeArc, $x2, $y2,
                    $x3, $y3, $innerR, $innerR, $largeArc, $x4, $y4
                );
            }

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
            'statuses' => $statusesWithGeometry,
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
        $ticketsRoute = (auth()->check() && auth()->user()?->role?->slug_identifier === 'admin') ? 'admin.tickets' : 'cwd.tickets';

        $workload = $departmentCounts->map(function ($row) use ($departments, $ticketsRoute) {
            $department = $departments->get($row->department_id);

            return [
                'label' => $department
                    ? $department->dept_name.($department->trashed() ? ' (archived)' : '')
                    : 'No department',
                'total' => (int) $row->total,
                'url' => route($ticketsRoute, [
                    'department' => $row->department_id ?? 'unassigned',
                    'status' => 'all',
                ]),
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
                [
                    'label' => 'Less than 24 hours',
                    'total' => (clone $active)->where('created_at', '>', $now->copy()->subDay())->count(),
                    'url' => route($ticketsRoute, ['aging' => 'under_24h', 'status' => 'all']),
                ],
                [
                    'label' => '1 to under 7 days',
                    'total' => (clone $active)->where('created_at', '<=', $now->copy()->subDay())->where('created_at', '>', $now->copy()->subDays(7))->count(),
                    'url' => route($ticketsRoute, ['aging' => '1_to_7_days', 'status' => 'all']),
                ],
                [
                    'label' => '7 days or more',
                    'total' => (clone $active)->where('created_at', '<=', $now->copy()->subDays(7))->count(),
                    'url' => route($ticketsRoute, ['aging' => '7_days_plus', 'status' => 'all']),
                ],
            ],
            'departments' => $workload->take(6)->all(),
            'other_departments' => $workload->skip(6)->sum('total'),
            'department_max' => max(1, $workload->max('total') ?? 0),
        ];
    }

    /**
     * Summarizes system-wide user counts, departments, teams, categories, and live online users for administrators.
     */
    public function adminSummary(): array
    {
        $usersTotal = User::query()->toBase()->count();
        $usersActive = User::query()->where('is_active', true)->toBase()->count();
        $deptsActive = Department::query()->count();
        $deptsArchived = Department::onlyTrashed()->count();
        $ticketsTotal = Ticket::query()->count();
        $teamsActive = Team::query()->count();
        $categoriesActive = TicketCategory::query()->count();

        // Calculate live online users (deduplicated union of active web sessions + active mobile Sanctum tokens)
        $sessionLifetimeSeconds = (int) config('session.lifetime', 120) * 60;
        $activeWebUserIds = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->timestamp - $sessionLifetimeSeconds)
            ->pluck('user_id');

        $activeMobileUserIds = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where('last_used_at', '>=', now()->subHours(24))
            ->pluck('tokenable_id');

        $onlineUsersCount = $activeWebUserIds->merge($activeMobileUserIds)->unique()->count();

        $metrics = [
            ['Users', $usersActive, 'Active staff accounts', route('admin.users'), 'bg-emerald-50 text-emerald-600', 'bg-emerald-500', 'users'],
            ['Departments', $deptsActive, 'Active divisions', route('admin.departments'), 'bg-sky-50 text-sky-600', 'bg-sky-500', 'department'],
            ['Field teams', $teamsActive, 'Operational crews', route('admin.teams'), 'bg-violet-50 text-violet-600', 'bg-violet-500', 'team'],
            ['Categories', $categoriesActive, 'Complaint types', route('admin.ticketCategories'), 'bg-amber-50 text-amber-600', 'bg-amber-500', 'category'],
            ['All tickets', $ticketsTotal, 'Lifetime records', route('admin.monitoring.index', ['category' => 'Tickets']), 'bg-blue-50 text-blue-600', 'bg-blue-600', 'ticket'],
            ['Online users', $onlineUsersCount, 'Active sessions & tokens', route('admin.monitoring.index', ['category' => 'auth']), 'bg-teal-50 text-teal-600', 'bg-teal-500', 'user'],
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
            'categories_active' => $categoriesActive,
            'online_users' => $onlineUsersCount,
            'system_metrics' => $metricsWithWidth,
            'metric_max' => $metricMax,
            'admin_controls' => $adminControls,
        ];
    }

    /**
     * Retrieves the top 5 ticket categories with the most open tickets for the leaderboard.
     * Accurately aggregates both predefined categories and open-ended/custom complaint categories.
     */
    public function topOpenCategories(): array
    {
        $rawCategories = Ticket::query()
            ->where('tickets.status', TicketStatus::OPEN->value)
            ->whereNull('tickets.deleted_at')
            ->leftJoin('ticket_categories', 'tickets.category_id', '=', 'ticket_categories.id')
            ->selectRaw("
                CASE 
                    WHEN tickets.other_category = 1 
                        THEN 'Other Category'
                    ELSE COALESCE(ticket_categories.category_name, 'Uncategorized')
                END as resolved_category_name,
                COUNT(*) as open_tickets_count
            ")
            ->groupBy('resolved_category_name')
            ->orderByDesc('open_tickets_count')
            ->limit(5)
            ->get();

        $maxCount = max(1, (int) ($rawCategories->max('open_tickets_count') ?? 0));
        $totalOpen = Ticket::query()->where('status', TicketStatus::OPEN->value)->count();

        $items = $rawCategories->values()->map(function ($row, $index) use ($maxCount) {
            return [
                'rank' => $index + 1,
                'name' => $row->resolved_category_name,
                'count' => (int) $row->open_tickets_count,
                'width_percent' => round(($row->open_tickets_count / $maxCount) * 100, 1),
            ];
        })->all();

        return [
            'total_open' => $totalOpen,
            'categories' => $items,
        ];
    }

    /**
     * Compiles system monitoring and audit telemetry from the activity_log table for administrators.
     */
    public function systemMonitoringSnapshot(): array
    {
        $todayStart = Carbon::today()->startOfDay();
        $past24Hours = Carbon::now()->subHours(24);

        $auditEventsToday = Activity::query()
            ->where('created_at', '>=', $todayStart)
            ->count();

        $securityAlerts = Activity::query()
            ->where('log_name', 'login_failed')
            ->where('created_at', '>=', $past24Hours)
            ->count();

        $staffLoginsToday = Activity::query()
            ->where('log_name', 'login_success')
            ->where('created_at', '>=', $todayStart)
            ->count();

        $accountMutationsToday = Activity::query()
            ->where('log_name', 'Users')
            ->where('created_at', '>=', $todayStart)
            ->count();

        $orgChangesToday = Activity::query()
            ->whereIn('log_name', ['Teams', 'Department'])
            ->where('created_at', '>=', $todayStart)
            ->count();

        $criticalActionsToday = Activity::query()
            ->whereIn('event', ['deleted', 'delete', 'archived', 'locked', 'force_deleted'])
            ->where('created_at', '>=', $todayStart)
            ->count();

        // Module activity distribution for today
        $moduleCounts = Activity::query()
            ->where('created_at', '>=', $todayStart)
            ->selectRaw('log_name, COUNT(*) as total')
            ->groupBy('log_name')
            ->pluck('total', 'log_name')
            ->all();

        $authCount = ($moduleCounts['login_success'] ?? 0)
            + ($moduleCounts['login_failed'] ?? 0)
            + ($moduleCounts['login_pass_account_deactivated'] ?? 0);

        $moduleDistribution = [
            ['label' => 'Authentication', 'total' => $authCount, 'color' => 'bg-emerald-500'],
            ['label' => 'Tickets & Dispatch', 'total' => (int) ($moduleCounts['Tickets'] ?? 0), 'color' => 'bg-blue-500'],
            ['label' => 'User Management', 'total' => (int) ($moduleCounts['Users'] ?? 0), 'color' => 'bg-teal-500'],
            ['label' => 'Teams & Crews', 'total' => (int) ($moduleCounts['Teams'] ?? 0), 'color' => 'bg-purple-500'],
            ['label' => 'Departments', 'total' => (int) ($moduleCounts['Department'] ?? 0), 'color' => 'bg-sky-500'],
            ['label' => 'Categories', 'total' => (int) ($moduleCounts['TicketCategory'] ?? 0), 'color' => 'bg-amber-500'],
        ];

        $moduleMax = max(1, (int) collect($moduleDistribution)->max('total'));

        $moduleDistributionWithWidth = collect($moduleDistribution)->map(function ($item) use ($moduleMax) {
            $item['width_percent'] = round(($item['total'] / $moduleMax) * 100, 1);

            return $item;
        })->all();

        // Recent 5 audit events
        $recentLogs = Activity::query()
            ->with(['causer.role'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($log) {
                $actor = $log->causer;
                $actorName = $actor->fullName;
                $actorRole = $actor?->role?->role_name ?? ($actor ? 'User' : 'System Automation');
                $initials = $actor
                    ? strtoupper(substr($actor->first_name ?? '', 0, 1).substr($actor->last_name ?? '', 0, 1))
                    : 'SY';

                return [
                    'id' => $log->id,
                    'actor_name' => $actorName,
                    'actor_role' => $actorRole,
                    'actor_initials' => $initials ?: 'US',
                    'description' => $log->description,
                    'event' => $log->event,
                    'time_display' => $log->created_at->diffForHumans(),
                    'created_at_time' => $log->created_at->format('h:i A'),
                    'url' => route('admin.monitoring.show', $log->id),
                ];
            })->all();

        return [
            'today' => Carbon::now()->format('M j, Y'),
            'timezone' => config('app.timezone'),
            'audit_events_today' => $auditEventsToday,
            'security_alerts' => $securityAlerts,
            'staff_logins_today' => $staffLoginsToday,
            'account_mutations_today' => $accountMutationsToday,
            'org_changes_today' => $orgChangesToday,
            'critical_actions_today' => $criticalActionsToday,
            'module_distribution' => $moduleDistributionWithWidth,
            'recent_logs' => $recentLogs,
        ];
    }
}
