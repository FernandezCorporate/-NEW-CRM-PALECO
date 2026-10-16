@props(['data'])

<figure data-animate class="ui-reveal panel p-6 md:p-7" style="--delay: 90ms" aria-labelledby="category-leaderboard-title">
    <figcaption class="flex items-start justify-between gap-4">
        <div>
            <p class="eyebrow">Complaint volume</p>
            <h2 id="category-leaderboard-title" class="mt-1 text-xl font-bold text-slate-900">Top open ticket categories</h2>
        </div>
        <p class="text-right">
            <data class="block text-2xl font-bold text-slate-900" value="{{ $data['total_open'] ?? 0 }}">
                {{ number_format($data['total_open'] ?? 0) }}
            </data>
            <span class="text-xs text-slate-500">total open</span>
        </p>
    </figcaption>
    <div class="mt-6 space-y-4" role="img" aria-label="Top categories with the most open service tickets">
        @forelse ($data['categories'] ?? [] as $index => $item)
            <div>
                <div class="mb-2 flex items-center justify-between gap-4 text-xs">
                    <span class="flex items-center gap-2 truncate font-semibold text-slate-700">
                        <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-600">
                            {{ $item['rank'] }}
                        </span>
                        <span class="truncate" title="{{ $item['name'] }}">{{ $item['name'] }}</span>
                    </span>
                    <strong class="shrink-0 tabular-nums text-slate-900">
                        <data value="{{ $item['count'] }}">{{ number_format($item['count']) }}</data>
                        <span class="text-[11px] font-normal text-slate-400">open</span>
                    </strong>
                </div>
                <div class="chart-row-track">
                    <div class="chart-row-fill" style="--chart-width: {{ $item['width_percent'] }}%; --bar-delay: {{ $index * 65 }}ms"></div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-slate-200 p-8 text-center">
                <x-icons.ticket class="mx-auto h-8 w-8 text-slate-300" />
                <p class="mt-2 text-sm font-semibold text-slate-700">No open tickets at this time</p>
                <p class="mt-1 text-xs text-slate-400">All service complaints have been assigned or resolved.</p>
            </div>
        @endforelse
    </div>
</figure>

