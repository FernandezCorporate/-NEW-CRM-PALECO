@extends('cwd.base.base')

@section('title', 'CWD Dashboard')

@section('content')
    <div class="max-w-7xl mx-auto space-y-8">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Welcome back, {{ auth()->user()->first_name ?? 'Officer' }}</p>
                <h1 class="page-title mt-2">PALECO Service Desk</h1>
                <p class="mt-2 text-sm text-slate-500">Receive, organize, and follow through on consumer service requests.</p>
            </div>
            <a href="{{ route('cwd.tickets.createForm') }}" class="inline-flex w-fit items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-700/15 transition hover:-translate-y-0.5 hover:bg-emerald-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Create ticket
            </a>
        </header>

        <section class="grid gap-4 md:grid-cols-3" aria-label="Ticket workflow">
            @php
                $statusTotals = collect($overview['statuses'])->pluck('total', 'key');
            @endphp
            @foreach ([
                ['Open tickets', $statusTotals['open'] ?? 0, 'Awaiting assignment or action', route('cwd.tickets', ['status' => 'open']), 'bg-emerald-50 text-emerald-600', 'M6 3h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2zm3 5h6m-6 4h6m-6 4h4'],
                ['In progress', $statusTotals['in_progress'] ?? 0, 'Currently handled by field teams', route('cwd.tickets', ['status' => 'in_progress']), 'bg-blue-50 text-blue-600', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['Escalations', ($statusTotals['pending_escalation'] ?? 0) + ($statusTotals['escalated'] ?? 0), 'Cases requiring added attention', route('cwd.escalations'), 'bg-amber-50 text-amber-600', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
            ] as $index => [$label, $value, $description, $url, $tone, $iconPath])
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
            <figure data-animate class="ui-reveal panel p-6 md:p-7" aria-labelledby="cwd-trend-title">
                <figcaption class="flex items-start justify-between gap-4">
                    <div><p class="eyebrow">7-day activity</p><h2 id="cwd-trend-title" class="mt-1 text-xl font-bold text-slate-900">Complaint intake</h2></div>
                    <p class="text-right"><span class="block text-2xl font-bold text-slate-900">{{ array_sum(array_column($overview['trend'], 'total')) }}</span><span class="text-xs text-slate-500">new tickets</span></p>
                </figcaption>
                <div class="mt-5 chart-columns" role="img" aria-label="Complaint intake over the last seven days">
                    @foreach ($overview['trend'] as $index => $day)
                        <div class="chart-column" style="--chart-height: {{ max(4, ($day['total'] / $overview['trend_max']) * 100) }}%; --bar-delay: {{ $index * 70 }}ms" title="{{ $day['date'] }}: {{ $day['total'] }} tickets">
                            <span class="chart-value">{{ $day['total'] }}</span><div class="chart-column-fill"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 grid grid-cols-7 gap-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                    @foreach ($overview['trend'] as $day)<span>{{ $day['label'] }}</span>@endforeach
                </div>
            </figure>

            <figure data-animate class="ui-reveal panel p-6 md:p-7" style="--delay: 90ms" aria-labelledby="status-chart-title">
                <figcaption><p class="eyebrow">Current workload</p><h2 id="status-chart-title" class="mt-1 text-xl font-bold text-slate-900">Ticket status mix</h2></figcaption>
                @php
                    $statusColors = ['bg-emerald-500', 'bg-sky-500', 'bg-blue-600', 'bg-amber-500', 'bg-orange-500', 'bg-violet-500', 'bg-slate-500'];
                @endphp
                <div class="mt-6 flex h-4 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Distribution of {{ $overview['total'] }} tickets by current status">
                    @foreach ($overview['statuses'] as $index => $status)
                        @if ($status['total'] > 0)
                            <span class="status-segment {{ $statusColors[$index] }}" style="--segment-width: {{ ($status['total'] / max(1, $overview['total'])) * 100 }}%; --segment-delay: {{ $index * 70 }}ms" title="{{ $status['label'] }}: {{ $status['total'] }}"></span>
                        @endif
                    @endforeach
                </div>
                <div class="mt-6 grid grid-cols-2 gap-x-5 gap-y-3">
                    @foreach ($overview['statuses'] as $index => $status)
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="flex min-w-0 items-center gap-2 text-slate-500"><i class="h-2 w-2 shrink-0 rounded-full {{ $statusColors[$index] }}"></i><span class="truncate">{{ $status['label'] }}</span></span>
                            <strong class="tabular-nums text-slate-800">{{ $status['total'] }}</strong>
                        </div>
                    @endforeach
                </div>
            </figure>
        </section>

        <section class="grid gap-6 lg:grid-cols-[1.35fr_1fr]">
            <div data-animate class="ui-reveal panel p-6 md:p-7">
                <p class="eyebrow">Quick actions</p>
                <h2 class="mt-1 text-xl font-bold text-slate-900">Keep requests moving</h2>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('cwd.tickets') }}" class="quick-link">
                        <span class="metric-icon !h-10 !w-10 bg-emerald-50 text-emerald-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5h6m-6 4h6m-8 4h10m-10 4h6M6 3h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2z" /></svg></span>
                        <span><strong class="block text-sm text-slate-800">Manage tickets</strong><small class="text-slate-500">View all service requests</small></span>
                    </a>
                    <a href="{{ route('cwd.escalations') }}" class="quick-link">
                        <span class="metric-icon !h-10 !w-10 bg-amber-50 text-amber-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.3 4.4L2.8 17.2A2 2 0 004.5 20h15a2 2 0 001.7-2.8L13.7 4.4a2 2 0 00-3.4 0z" /></svg></span>
                        <span><strong class="block text-sm text-slate-800">Review escalations</strong><small class="text-slate-500">Handle priority cases</small></span>
                    </a>
                </div>
            </div>

            <aside data-animate class="ui-reveal panel overflow-hidden" style="--delay: 90ms">
                <div class="bg-gradient-to-br from-emerald-900 to-emerald-700 p-6 text-white md:p-7">
                    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-emerald-200">Service reminder</p>
                    <h2 class="mt-2 text-xl font-bold">Every ticket represents a member waiting for help.</h2>
                    <p class="mt-3 text-sm leading-6 text-emerald-100">Keep details complete and statuses current so field teams can respond efficiently.</p>
                </div>
                <div class="px-6 py-4 text-xs font-medium text-slate-500">{{ now()->format('l, F j, Y') }}</div>
            </aside>
        </section>
    </div>
@endsection
