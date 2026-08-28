@extends('admin.base.base')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="max-w-7xl mx-auto space-y-8">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Good day, {{ auth()->user()->first_name ?? 'Administrator' }}</p>
                <h1 class="page-title mt-2">PALECO Control Center</h1>
                <p class="mt-2 text-sm text-slate-500">Manage people, organizational structure, and service operations from one workspace.</p>
            </div>
            <div class="status-live inline-flex w-fit items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                System operational
            </div>
        </header>

        @php
            $systemMetrics = [
                ['Users', $summary['users_total'], 'Total accounts', route('admin.users'), 'bg-emerald-50 text-emerald-600', 'bg-emerald-500', 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2m7-10a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75'],
                ['Active users', $summary['users_active'], 'Enabled accounts', route('admin.users'), 'bg-teal-50 text-teal-600', 'bg-teal-500', 'M9 12l2 2 4-4m5-4a11.96 11.96 0 01-8-3 11.96 11.96 0 01-8 3c0 5.25 3.4 10 8 11.5 4.6-1.5 8-6.25 8-11.5z'],
                ['Active departments', $summary['departments_active'], 'Current divisions', route('admin.departments'), 'bg-sky-50 text-sky-600', 'bg-sky-500', 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1'],
                ['Archived departments', $summary['departments_archived'], 'Retired divisions', route('admin.departments', ['filter' => 'archived']), 'bg-amber-50 text-amber-600', 'bg-amber-500', 'M5 8h14m-13 0v11h12V8M8 5h8l1 3H7l1-3zm2 7h4'],
                ['Total tickets', $summary['tickets_total'], 'All service requests', route('admin.monitoring.index'), 'bg-blue-50 text-blue-600', 'bg-blue-600', 'M6 3h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2zm3 5h6m-6 4h6m-6 4h4'],
                ['Active teams', $summary['teams_active'], 'Operational crews', route('admin.teams'), 'bg-violet-50 text-violet-600', 'bg-violet-500', 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2m8-10a4 4 0 100-8 4 4 0 000 8zm14 10v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75'],
            ];
            $systemMetricMax = max(1, ...array_column($systemMetrics, 1));
        @endphp

        <section class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-6" aria-label="System totals">
            @foreach ($systemMetrics as $index => [$label, $value, $description, $url, $tone, $barTone, $iconPath])
                <a href="{{ $url }}" data-animate class="ui-reveal metric-card group block" style="--delay: {{ $index * 70 }}ms">
                    <div class="flex items-start justify-between gap-4">
                        <div class="metric-icon {{ $tone }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="{{ $iconPath }}" /></svg>
                        </div>
                        <svg class="h-4 w-4 text-slate-300 transition group-hover:translate-x-1 group-hover:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </div>
                    <p class="mt-5 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($value) }}</p>
                    <h2 class="mt-1 text-sm font-bold text-slate-800">{{ $label }}</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                </a>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.45fr_1fr]">
            <figure data-animate class="ui-reveal panel p-6 md:p-7" aria-labelledby="admin-trend-title">
                <figcaption class="flex items-start justify-between gap-4">
                    <div>
                        <p class="eyebrow">7-day activity</p>
                        <h2 id="admin-trend-title" class="mt-1 text-xl font-bold text-slate-900">New tickets received</h2>
                    </div>
                    <p class="text-right"><span class="block text-2xl font-bold text-slate-900">{{ array_sum(array_column($overview['trend'], 'total')) }}</span><span class="text-xs text-slate-500">this week</span></p>
                </figcaption>
                <div class="mt-5 chart-columns" role="img" aria-label="Ticket intake over the last seven days">
                    @foreach ($overview['trend'] as $index => $day)
                        <div class="chart-column" style="--chart-height: {{ max(4, ($day['total'] / $overview['trend_max']) * 100) }}%; --bar-delay: {{ $index * 70 }}ms" title="{{ $day['date'] }}: {{ $day['total'] }} tickets">
                            <span class="chart-value">{{ $day['total'] }}</span>
                            <div class="chart-column-fill"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 grid grid-cols-7 gap-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                    @foreach ($overview['trend'] as $day)<span>{{ $day['label'] }}</span>@endforeach
                </div>
            </figure>

            <figure data-animate class="ui-reveal panel p-6 md:p-7" style="--delay: 90ms" aria-labelledby="system-chart-title">
                <figcaption>
                    <p class="eyebrow">System footprint</p>
                    <h2 id="system-chart-title" class="mt-1 text-xl font-bold text-slate-900">Operational overview</h2>
                </figcaption>
                <div class="mt-6 space-y-4" role="img" aria-label="Comparison of users, departments, tickets, and teams">
                    @foreach ($systemMetrics as $index => [$label, $value, $description, $url, $tone, $barTone])
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-4 text-xs">
                                <span class="truncate font-semibold text-slate-700">{{ $label }}</span>
                                <span class="font-bold tabular-nums text-slate-900">{{ number_format($value) }}</span>
                            </div>
                            <div class="chart-row-track"><div class="chart-row-fill {{ $barTone }}" style="--chart-width: {{ ($value / $systemMetricMax) * 100 }}%; --bar-delay: {{ $index * 65 }}ms"></div></div>
                        </div>
                    @endforeach
                </div>
            </figure>
        </section>

        @php
            $controlCatalog = [
                ['add-user', 'Add new user', 'Create a staff account', route('admin.users.createForm'), 'bg-emerald-50 text-emerald-600', 'M18 9v3m0 0v3m0-3h3m-3 0h-3M9 11a4 4 0 100-8 4 4 0 000 8zm-6 10a6 6 0 0112 0'],
                ['users', 'Manage users', 'Review staff accounts', route('admin.users'), 'bg-teal-50 text-teal-600', 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2m7-10a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87'],
                ['departments', 'Departments', 'Maintain service divisions', route('admin.departments'), 'bg-sky-50 text-sky-600', 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1'],
                ['teams', 'Field teams', 'Coordinate operational crews', route('admin.teams'), 'bg-violet-50 text-violet-600', 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2m8-10a4 4 0 100-8 4 4 0 000 8zm14 10v-2a4 4 0 00-3-3.87'],
                ['categories', 'Ticket categories', 'Organize complaint types', route('admin.ticketCategories'), 'bg-amber-50 text-amber-600', 'M9 3H5a2 2 0 00-2 2v4a2 2 0 00.59 1.41l9 9a2 2 0 002.82 0l4-4a2 2 0 000-2.82l-9-9A2 2 0 009 3zM7 7h.01'],
                ['monitoring', 'System monitoring', 'Review system activity', route('admin.monitoring.index'), 'bg-blue-50 text-blue-600', 'M3 3v18h18M7 16l4-5 3 3 5-7'],
            ];
            $defaultControls = ['add-user', 'monitoring'];
        @endphp

        <section class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <div data-animate data-dashboard-controls data-storage-key="paleco-admin-controls-{{ auth()->id() }}" data-default-controls='@json($defaultControls)' class="ui-reveal panel p-6 md:p-7">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="eyebrow">Workspace</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">Administrative control center</h2>
                    </div>
                    <button type="button" data-controls-open class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 text-xs font-bold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100 focus:outline-none focus:ring-4 focus:ring-emerald-500/10">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7H5" /></svg>
                        Add controls
                    </button>
                </div>
                <div data-controls-grid class="mt-6 grid gap-3 sm:grid-cols-2">
                    @foreach ($controlCatalog as [$id, $label, $description, $url, $tone, $iconPath])
                        <a href="{{ $url }}" data-control-item="{{ $id }}" class="quick-link {{ in_array($id, $defaultControls, true) ? '' : 'hidden' }}">
                            <span class="metric-icon !h-10 !w-10 {{ $tone }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="{{ $iconPath }}" /></svg></span>
                            <span class="min-w-0"><strong class="block truncate text-sm text-slate-800">{{ $label }}</strong><small class="block truncate text-slate-500">{{ $description }}</small></span>
                        </a>
                    @endforeach
                </div>
                <p data-controls-empty class="mt-6 hidden rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500">No controls selected. Use “Add controls” to choose shortcuts.</p>
                <p data-controls-feedback class="sr-only" aria-live="polite"></p>

                <dialog data-controls-dialog class="control-dialog w-[calc(100%-2rem)] max-w-xl rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/45 backdrop:backdrop-blur-sm">
                    <div class="border-b border-slate-100 px-6 py-5">
                        <p class="eyebrow">Personalize workspace</p>
                        <h3 class="mt-1 text-xl font-bold text-slate-900">Choose dashboard controls</h3>
                        <p class="mt-1 text-sm text-slate-500">Select up to four shortcuts. Your choices are saved for this account on this device.</p>
                    </div>
                    <div class="grid gap-2 px-6 py-5 sm:grid-cols-2">
                        @foreach ($controlCatalog as [$id, $label, $description, $url, $tone, $iconPath])
                            <label class="control-choice flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3.5 transition hover:border-emerald-300 hover:bg-emerald-50/50">
                                <input type="checkbox" value="{{ $id }}" data-control-choice class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 accent-emerald-600" {{ in_array($id, $defaultControls, true) ? 'checked' : '' }}>
                                <span><strong class="block text-sm text-slate-800">{{ $label }}</strong><small class="mt-0.5 block leading-5 text-slate-500">{{ $description }}</small></span>
                            </label>
                        @endforeach
                    </div>
                    <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <p data-controls-count class="text-xs font-semibold text-slate-500">2 of 4 selected</p>
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" data-controls-reset class="min-h-10 rounded-lg px-3 text-xs font-semibold text-slate-500 transition hover:bg-slate-200/70 hover:text-slate-800">Reset defaults</button>
                            <button type="button" data-controls-cancel class="min-h-10 rounded-lg border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</button>
                            <button type="button" data-controls-save class="min-h-10 rounded-lg bg-emerald-600 px-4 text-xs font-semibold text-white transition hover:bg-emerald-700">Save controls</button>
                        </div>
                    </div>
                </dialog>
            </div>

            <aside data-animate class="ui-reveal panel p-6 md:p-7" style="--delay: 90ms">
                <p class="eyebrow">Today</p>
                <h2 class="mt-1 text-xl font-bold text-slate-900">{{ now()->format('l, F j') }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">Your administration tools are ready. Use the shortcuts above to maintain the system's core records.</p>
                <div class="mt-6 border-t border-slate-100 pt-5 text-xs text-slate-400">PALECO Consumer Welfare Desk</div>
            </aside>
        </section>
    </div>
@endsection
