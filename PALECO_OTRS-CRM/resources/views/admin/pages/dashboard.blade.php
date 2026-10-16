@extends('admin.base.base')

@section('workspace-kind', 'dashboard')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="max-w-7xl mx-auto space-y-8">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Good day, {{ auth()->user()->first_name ?? 'Administrator' }}</p>
                <h1 class="page-title mt-2">PALECO Control Center</h1>
                <p class="mt-2 text-sm text-slate-500">Manage people, organizational structure, and service operations from one workspace.</p>
            </div>
        </header>

        <!-- Telemetry Metric Cards Grid -->
        <section class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-6" aria-label="System totals">
            @foreach ($summary['system_metrics'] ?? [] as $index => [$label, $value, $description, $url, $tone, $barTone, $icon, $widthPercent])
                <a href="{{ $url }}" data-animate class="ui-reveal group block" style="--delay: {{ $index * 70 }}ms">
                    <article class="metric-card h-full">
                        <header class="flex items-start justify-between gap-4">
                            <div class="metric-icon {{ $tone }}">
                                @if (str_starts_with($icon, 'M'))
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="{{ $icon }}" /></svg>
                                @else
                                    <x-dynamic-component :component="'icons.' . $icon" class="h-5 w-5" />
                                @endif
                            </div>
                            <x-icons.chevron-right class="h-4 w-4 text-slate-300 transition group-hover:translate-x-1 group-hover:text-emerald-600" />
                        </header>
                        <data class="mt-5 block text-3xl font-bold tracking-tight text-slate-900" value="{{ $value }}">
                            {{ number_format($value) }}
                        </data>
                        <h2 class="mt-1 text-sm font-bold text-slate-800">{{ $label }}</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                    </article>
                </a>
            @endforeach
        </section>

        <!-- Charts: 7-Day Trend & System Footprint -->
        <section class="grid gap-6 xl:grid-cols-[1.45fr_1fr]">
            <figure data-animate class="ui-reveal panel p-6 md:p-7" aria-labelledby="admin-trend-title">
                <figcaption class="flex items-start justify-between gap-4">
                    <div>
                        <p class="eyebrow">7-day activity</p>
                        <h2 id="admin-trend-title" class="mt-1 text-xl font-bold text-slate-900">New tickets received</h2>
                    </div>
                    <p class="text-right">
                        <data class="block text-2xl font-bold text-slate-900" value="{{ $overview['trend_sum'] ?? 0 }}">
                            {{ $overview['trend_sum'] ?? 0 }}
                        </data>
                        <span class="text-xs text-slate-500">this week</span>
                    </p>
                </figcaption>
                <div class="mt-5 chart-columns" role="img" aria-label="Ticket intake over the last seven days">
                    @foreach ($overview['trend'] as $index => $day)
                        <div class="chart-column" style="--chart-height: {{ $day['height_percent'] ?? 4 }}%; --bar-delay: {{ $index * 70 }}ms" title="{{ $day['date'] }}: {{ $day['total'] }} tickets">
                            <span class="chart-value">{{ $day['total'] }}</span>
                            <div class="chart-column-fill"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 grid grid-cols-7 gap-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                    @foreach ($overview['trend'] as $day)<span>{{ $day['label'] }}</span>@endforeach
                </div>
            </figure>

            <!-- Top Open Ticket Categories Leaderboard -->
            <x-dashboard-category-leaderboard :data="$categoryLeaderboard" />
        </section>

        <!-- System Monitoring & Security Telemetry Island -->
        <x-dashboard-monitoring :data="$monitoring" />

        <!-- Quick Action Panels & Workspace Summary -->
        <section class="admin-action-panels grid gap-6 xl:grid-cols-2">
            <x-dashboard-controls :control-catalog="$summary['admin_controls'] ?? []" :default-controls="['add-user', 'monitoring']"
                :storage-key="'paleco-admin-controls-' . auth()->id()" title="Administrative control center" />

            <aside data-animate class="ui-reveal panel flex flex-col overflow-hidden" style="--delay: 90ms" aria-labelledby="admin-workspace-title">
                <div class="bg-gradient-to-br from-emerald-900 to-emerald-700 px-6 py-5 text-white md:px-7">
                    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-emerald-200">Organization at a glance</p>
                    <h2 id="admin-workspace-title" class="mt-2 text-xl font-bold">People, departments & teams</h2>
                </div>
                <ul class="flex flex-1 flex-col divide-y divide-slate-100">
                    @foreach ([
                        ['Active accounts', $summary['users_active'], number_format($summary['users_total']) . ' total accounts · Manage access', route('admin.users')],
                        ['Active departments', $summary['departments_active'], number_format($summary['departments_archived']) . ' archived · Manage divisions', route('admin.departments')],
                        ['Active field teams', $summary['teams_active'], 'Review crews and their members', route('admin.teams')],
                    ] as [$label, $count, $hint, $url])
                        <li class="flex flex-1">
                            <a href="{{ $url }}" class="group flex w-full items-center gap-4 px-6 py-3 md:px-7 transition-colors hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-emerald-600">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-slate-800">{{ $label }}</span>
                                    <span class="mt-1 block text-xs leading-5 text-slate-500">{{ $hint }}</span>
                                </span>
                                <strong class="text-xl font-bold tabular-nums text-emerald-700">
                                    <data value="{{ $count }}">{{ number_format($count) }}</data>
                                </strong>
                                <x-icons.chevron-right class="h-4 w-4 shrink-0 text-slate-400 group-hover:text-emerald-700 transition-colors" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </aside>
        </section>
    </div>
@endsection
