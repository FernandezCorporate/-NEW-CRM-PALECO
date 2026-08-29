<?php

namespace App\Services\Web\Dashboard;

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
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
            'total' => collect($statuses)->sum('total'),
            'statuses' => $statuses,
            'trend' => $trend,
            'trend_max' => max(1, collect($trend)->max('total')),
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
