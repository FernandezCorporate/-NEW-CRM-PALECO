@props(['data'])

<section class="space-y-6" aria-labelledby="monitoring-title">
    <header class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Audit & Security</p>
            <h2 id="monitoring-title" class="mt-1 text-xl font-bold text-slate-900">System Monitoring & Activity Trail</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $data['today'] }} · {{ $data['timezone'] }} · Real-time activity log telemetry across all user levels.</p>
        </div>
        <a href="{{ route('admin.monitoring.index') }}" class="action-link inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-800 transition-colors">
            <span>Explore full audit log</span>
            <x-icons.chevron-right class="h-3.5 w-3.5" />
        </a>
    </header>

    <!-- Telemetry Definition List (No Outer Borders) -->
    <dl class="grid grid-cols-2 gap-x-6 gap-y-5 border-y border-slate-200 py-6 md:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['Audit events today', $data['audit_events_today'], 'Total logged actions'],
            ['Security alerts', $data['security_alerts'], 'Failed logins (24h)'],
            ['Staff logins today', $data['staff_logins_today'], 'Successful sessions'],
            ['Account mutations', $data['account_mutations_today'], 'User creates & edits'],
            ['Organizational changes', $data['org_changes_today'], 'Teams & Department updates'],
            ['Critical actions', $data['critical_actions_today'], 'Deletions & archives'],
        ] as [$label, $value, $hint])
            <div>
                <dt class="text-xs font-semibold text-slate-600">{{ $label }}</dt>
                <dd class="mt-2 text-2xl font-bold tabular-nums {{ $label === 'Security alerts' && $value > 0 ? 'text-rose-600' : 'text-slate-900' }}">
                    <data value="{{ $value }}">{{ number_format($value) }}</data>
                </dd>
                <dd class="mt-1 text-xs leading-5 text-slate-500">{{ $hint }}</dd>
            </div>
        @endforeach
    </dl>

    <!-- Dual Analytical Sub-Panels -->
    <div class="grid gap-8 lg:grid-cols-2">
        <!-- Module Distribution -->
        <figure data-animate class="ui-reveal min-w-0" aria-labelledby="module-distribution-title">
            <figcaption>
                <h3 id="module-distribution-title" class="text-base font-bold text-slate-900">Activity by module</h3>
                <p class="mt-1 text-xs leading-5 text-slate-500">Distribution of today's recorded actions across system domains.</p>
            </figcaption>
            <ul class="mt-5 space-y-4">
                @forelse ($data['module_distribution'] ?? [] as $index => $row)
                    <li>
                        <div class="mb-2 flex items-start justify-between gap-4 text-sm">
                            <span class="min-w-0 break-words font-medium text-slate-700">{{ $row['label'] }}</span>
                            <strong class="shrink-0 tabular-nums text-slate-900">
                                <data value="{{ $row['total'] }}">{{ number_format($row['total']) }}</data>
                            </strong>
                        </div>
                        <div class="chart-row-track" aria-hidden="true">
                            <div class="chart-row-fill {{ $row['color'] }}" style="--chart-width: {{ $row['width_percent'] }}%; --bar-delay: {{ $index * 65 }}ms"></div>
                        </div>
                    </li>
                @empty
                    <li class="border-l-2 border-slate-300 pl-4 text-sm text-slate-500">No activity recorded today.</li>
                @endforelse
            </ul>
        </figure>

        <!-- Recent Audit Events Feed -->
        <div data-animate class="ui-reveal min-w-0" aria-labelledby="recent-audit-title">
            <div class="flex items-center justify-between">
                <div>
                    <h3 id="recent-audit-title" class="text-base font-bold text-slate-900">Recent audit events</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Latest administrative and user actions across the system.</p>
                </div>
            </div>
            <ul class="mt-5 divide-y divide-slate-100 rounded-lg border border-slate-200/70 bg-white shadow-2xs">
                @forelse ($data['recent_logs'] ?? [] as $log)
                    <li class="flex items-center justify-between gap-3 p-3.5 hover:bg-slate-50/60 transition-colors">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-[11px] shrink-0 border border-slate-200">
                                {{ $log['actor_initials'] }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-slate-800 truncate">{{ $log['actor_name'] }}</span>
                                    <span class="text-[10px] font-medium text-slate-400">· {{ $log['time_display'] }}</span>
                                </div>
                                <p class="text-xs text-slate-500 truncate mt-0.5" title="{{ $log['description'] }}">{{ $log['description'] }}</p>
                            </div>
                        </div>
                        <a href="{{ $log['url'] }}" class="shrink-0 text-xs font-medium text-emerald-600 hover:text-emerald-700 hover:underline">
                            Details
                        </a>
                    </li>
                @empty
                    <li class="p-6 text-center text-xs text-slate-400">
                        No activity records found.
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
</section>

