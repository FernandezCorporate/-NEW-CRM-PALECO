<div>
    <!-- Workflow Metric Cards Grid -->
    <section class="grid gap-4 md:grid-cols-3" aria-label="Ticket workflow">
        @foreach ($overview['workflow_cards'] as $index => $card)
            <a href="{{ $card['url'] }}" data-animate class="ui-reveal group block" style="--delay: {{ $index * 70 }}ms">
                <article class="metric-card h-full">
                    <header class="flex items-start justify-between gap-4">
                        <div class="metric-icon {{ $card['tone'] }}">
                            <x-dynamic-component :component="'icons.' . $card['icon']" class="h-5 w-5" />
                        </div>
                        <x-icons.chevron-right class="h-4 w-4 text-slate-300 transition group-hover:translate-x-1 group-hover:text-emerald-600" />
                    </header>
                    <data class="mt-5 block text-3xl font-bold tracking-tight text-slate-900" value="{{ $card['value'] }}">
                        {{ number_format($card['value']) }}
                    </data>
                    <h2 class="mt-1 text-sm font-bold text-slate-800">{{ $card['label'] }}</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $card['description'] }}</p>
                </article>
            </a>
        @endforeach
    </section>

    <!-- Telemetry Charts Section -->
    <section class="grid gap-6 xl:grid-cols-[1.45fr_1fr] mt-8">
        <figure data-animate class="ui-reveal panel p-6 md:p-7" aria-labelledby="cwd-trend-title">
            <figcaption class="flex items-start justify-between gap-4">
                <div>
                    <p class="eyebrow">7-day activity</p>
                    <h2 id="cwd-trend-title" class="mt-1 text-xl font-bold text-slate-900">Complaint intake</h2>
                </div>
                <p class="text-right">
                    <data class="block text-2xl font-bold text-slate-900" value="{{ $overview['trend_sum'] }}">
                        {{ $overview['trend_sum'] }}
                    </data>
                    <span class="text-xs text-slate-500">new tickets</span>
                </p>
            </figcaption>
            <div class="mt-5 chart-columns" role="img" aria-label="Complaint intake over the last seven days">
                @foreach ($overview['trend'] as $index => $day)
                    <div class="chart-column" style="--chart-height: {{ $day['height_percent'] }}%; --bar-delay: {{ $index * 70 }}ms" title="{{ $day['date'] }}: {{ $day['total'] }} tickets">
                        <span class="chart-value">{{ $day['total'] }}</span>
                        <div class="chart-column-fill"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 grid grid-cols-7 gap-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                @foreach ($overview['trend'] as $day)<span>{{ $day['label'] }}</span>@endforeach
            </div>
        </figure>

        <figure data-animate class="ui-reveal panel p-6 md:p-7" style="--delay: 90ms" aria-labelledby="status-chart-title">
            <figcaption>
                <p class="eyebrow">Current workload</p>
                <h2 id="status-chart-title" class="mt-1 text-xl font-bold text-slate-900">Ticket status mix</h2>
            </figcaption>
            <div class="mt-6 flex h-4 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Distribution of {{ $overview['total'] }} tickets by current status">
                @foreach ($overview['statuses'] as $index => $status)
                    @if ($status['total'] > 0)
                        <span class="status-segment {{ $status['chart_class'] }}" style="--segment-width: {{ $status['width_percent'] }}%; --segment-delay: {{ $index * 70 }}ms" title="{{ $status['label'] }}: {{ $status['total'] }}"></span>
                    @endif
                @endforeach
            </div>
            <div class="mt-6 grid grid-cols-2 gap-x-5 gap-y-3">
                @foreach ($overview['statuses'] as $index => $status)
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="flex min-w-0 items-center gap-2 text-slate-500">
                            <i class="h-2 w-2 shrink-0 rounded-full {{ $status['dot_class'] }}"></i>
                            <span class="truncate">{{ $status['label'] }}</span>
                        </span>
                        <strong class="tabular-nums text-slate-800">
                            <data value="{{ $status['total'] }}">{{ $status['total'] }}</data>
                        </strong>
                    </div>
                @endforeach
            </div>
        </figure>
    </section>

    <!-- Operations Overview Component -->
    <div class="mt-8">
        <x-dashboard-operations :data="$overview['operations']" />
    </div>
</div>