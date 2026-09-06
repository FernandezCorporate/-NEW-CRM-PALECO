<?php

namespace App\Services\Web\Dashboard;

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketEscalation;
use App\Models\TicketAccomplishment;
use App\Models\User;
use Carbon\Carbon;

class DashboardService
{
    public function ticketOverview(): array
    {
        $statusCounts = Ticket::toBase()->pluck('status')->countBy();

        $statuses = collect(TicketStatus::cases())->map(fn (TicketStatus $status) => [
            'key' => $status->value,
            'label' => $status->label(),
            'total' => (int) ($statusCounts[$status->value] ?? 0),
        ])->values()->all();

        $trend = collect(range(6, 0))->map(function (int $daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'label' => $date->format('D'),
                'date' => $date->format('M j'),
                'total' => Ticket::query()->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])->count(),
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

    public function operationsSnapshot(): array
    {
        $now = Carbon::now();
        $active = Ticket::query()->whereNotIn('status', ['resolved', 'closed']);
        $departmentCounts = (clone $active)->selectRaw('department_id, COUNT(*) as total')
            ->groupBy('department_id')->get();
        $departments = Department::withTrashed()->whereIn('id', $departmentCounts->pluck('department_id')->filter())
            ->get()->keyBy('id');
        $workload = $departmentCounts->map(function ($row) use ($departments) {
            $department = $departments->get($row->department_id);

            return [
                'label' => $department
                    ? $department->dept_name . ($department->trashed() ? ' (archived)' : '')
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
            'without_team' => (clone $active)->whereNull('team_id')->count(),
            'pending_escalations' => TicketEscalation::query()->where('status', 'pending')->whereHas('ticket')->count(),
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
