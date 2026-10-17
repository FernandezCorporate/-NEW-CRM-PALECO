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

        <figure data-animate class="ui-reveal panel p-6 md:p-7 flex flex-col justify-between" style="--delay: 90ms" aria-labelledby="status-chart-title">
            <figcaption class="flex items-start justify-between gap-4">
                <div>
                    <p class="eyebrow">Current workload</p>
                    <h2 id="status-chart-title" class="mt-1 text-xl font-bold text-slate-900">Ticket status mix</h2>
                </div>
                <div class="text-right">
                    <span class="block text-2xl font-bold text-slate-900 tabular-nums">
                        {{ number_format($overview['total']) }}
                    </span>
                    <span class="text-xs text-slate-500">total tickets</span>
                </div>
            </figcaption>

            <!-- Interactive Pie Graph Container -->
            <div class="my-auto py-3 flex flex-col sm:flex-row items-center justify-center gap-6 relative" id="status-pie-container">
                <!-- SVG Chart & Center Ring Readout -->
                <div class="relative w-44 h-44 sm:w-48 sm:h-48 shrink-0 flex items-center justify-center">
                    <svg viewBox="0 0 200 200" 
                         class="w-full h-full overflow-visible drop-shadow-2xs pie-chart-svg" 
                         role="img" 
                         aria-label="Pie chart showing distribution of {{ $overview['total'] }} tickets by status">
                        @if ($overview['total'] === 0)
                            <!-- Empty State Ring -->
                            <circle cx="100" cy="100" r="68" fill="none" stroke="#e2e8f0" stroke-width="36" stroke-dasharray="4 4" />
                        @else
                            @foreach ($overview['statuses'] as $index => $status)
                                @if ($status['total'] > 0 && !empty($status['path_d']))
                                    <path d="{{ $status['path_d'] }}"
                                          fill="{{ $status['color_hex'] }}"
                                          stroke="#ffffff"
                                          stroke-width="2.5"
                                          stroke-linejoin="round"
                                          class="pie-slice transition-all duration-200 cursor-pointer"
                                          data-status-key="{{ $status['key'] }}"
                                          data-label="{{ $status['label'] }}"
                                          data-total="{{ $status['total'] }}"
                                          data-percent="{{ $status['width_percent'] }}%"
                                          data-color="{{ $status['color_hex'] }}"
                                          data-url="{{ $status['filter_url'] }}"
                                          role="graphics-symbol"
                                          aria-label="{{ $status['label'] }}: {{ $status['total'] }} ({{ $status['width_percent'] }}%)">
                                        <title>{{ $status['label'] }}: {{ $status['total'] }} ({{ $status['width_percent'] }}%)</title>
                                    </path>
                                @endif
                            @endforeach
                        @endif
                    </svg>

                    <!-- Center Readout -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center select-none" id="pie-center-display">
                        <span id="pie-center-value" class="text-2xl font-bold tracking-tight text-slate-800 tabular-nums transition-colors duration-150">
                            {{ number_format($overview['total']) }}
                        </span>
                        <span id="pie-center-label" class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mt-0.5 truncate max-w-[100px] transition-colors duration-150">
                            Tickets
                        </span>
                    </div>

                    <!-- Dynamic Floating Tooltip -->
                    <div id="pie-interactive-tooltip"
                         class="pointer-events-none absolute z-30 opacity-0 transition-opacity duration-150 -translate-x-1/2 -translate-y-full mb-3 rounded-lg bg-slate-900/95 text-white px-3 py-1.5 text-xs shadow-xl backdrop-blur-xs border border-slate-700/60 whitespace-nowrap"
                         style="left: 50%; top: 0;">
                        <div class="flex items-center gap-1.5 font-bold">
                            <span class="w-2 h-2 rounded-full inline-block" id="pie-tooltip-dot"></span>
                            <span id="pie-tooltip-label"></span>
                        </div>
                        <div class="text-[11px] text-slate-300 mt-0.5 flex items-center justify-between gap-3">
                            <span class="font-bold text-white tabular-nums" id="pie-tooltip-total"></span>
                            <span class="text-slate-400 tabular-nums" id="pie-tooltip-percent"></span>
                        </div>
                    </div>
                </div>

                <!-- Status Legend Grid -->
                <div class="w-full sm:w-auto flex-1 grid grid-cols-2 gap-x-3 gap-y-1.5">
                    @foreach ($overview['statuses'] as $index => $status)
                        <a href="{{ $status['filter_url'] }}" 
                           class="status-legend-item flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg text-xs hover:bg-slate-50 transition-colors group cursor-pointer"
                           data-status-key="{{ $status['key'] }}"
                           data-label="{{ $status['label'] }}"
                           data-total="{{ $status['total'] }}"
                           data-percent="{{ $status['width_percent'] }}%"
                           data-color="{{ $status['color_hex'] }}"
                           title="Filter tickets by {{ $status['label'] }}">
                            <span class="flex min-w-0 items-center gap-2 text-slate-600 group-hover:text-slate-900 transition-colors">
                                <i class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $status['color_hex'] }}"></i>
                                <span class="truncate font-medium">{{ $status['label'] }}</span>
                            </span>
                            <strong class="tabular-nums text-slate-700 group-hover:text-slate-900 text-right ml-1 font-semibold">
                                <data value="{{ $status['total'] }}">{{ $status['total'] }}</data>
                            </strong>
                        </a>
                    @endforeach
                </div>
            </div>
        </figure>
    </section>

    <!-- Operations Overview Component -->
    <div class="mt-8">
        <x-dashboard-operations :data="$overview['operations']" />
    </div>
</div>