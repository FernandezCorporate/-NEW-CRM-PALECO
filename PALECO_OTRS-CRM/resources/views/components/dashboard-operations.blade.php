@props(['data'])

<section class="space-y-6" aria-labelledby="operations-title">
    <header>
        <p class="eyebrow">Service operations</p>
        <h2 id="operations-title" class="mt-1 text-xl font-bold text-slate-900">What needs attention</h2>
        <p class="mt-2 text-sm text-slate-500">{{ $data['today'] }} · {{ $data['timezone'] }} · Updated when you load this page.</p>
    </header>
    <dl class="grid grid-cols-2 gap-x-6 gap-y-5 border-y border-slate-200 py-6 md:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['Received today', $data['received_today'], 'Newly created tickets'],
            ['Closed today', $data['closed_today'], 'Based on closure date'],
            ['Unresolved tickets', $data['active'], 'Excludes resolved and closed'],
            ['Without a team', $data['without_team'], 'Unresolved tickets only'],
            ['Escalation reviews', $data['pending_escalations'], 'Pending decisions'],
            ['Report reviews', $data['pending_reports'], 'Pending accomplishments'],
        ] as [$label, $value, $hint])
            <div>
                <dt class="text-xs font-semibold text-slate-600">{{ $label }}</dt>
                <dd class="mt-2 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($value) }}</dd>
                <dd class="mt-1 text-xs leading-5 text-slate-500">{{ $hint }}</dd>
            </div>
        @endforeach
    </dl>
    <div class="grid gap-8 lg:grid-cols-2">
        @foreach ([
            ['department-workload', 'Department workload', 'Up to six departments with the most unresolved tickets.', $data['departments'], $data['department_max']],
            ['ticket-age', 'Age of unresolved tickets', 'Time since creation, not an overdue or service-level measure.', $data['aging'], max(1, $data['active'])],
        ] as [$id, $title, $description, $rows, $maximum])
            <figure data-animate class="ui-reveal min-w-0" aria-labelledby="{{ $id }}-title">
                <figcaption>
                    <h3 id="{{ $id }}-title" class="text-base font-bold text-slate-900">{{ $title }}</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                </figcaption>
                <ul class="mt-5 space-y-4">
                    @forelse ($rows as $index => $row)
                        <li>
                            <div class="mb-2 flex items-start justify-between gap-4 text-sm">
                                <span class="min-w-0 break-words font-medium text-slate-700">{{ $row['label'] }}</span>
                                <strong class="shrink-0 tabular-nums text-slate-900">{{ number_format($row['total']) }}</strong>
                            </div>
                            <div class="chart-row-track" aria-hidden="true"><div class="chart-row-fill {{ $id === 'ticket-age' ? ['bg-teal-500', 'bg-sky-600', 'bg-amber-500'][$index] : 'bg-emerald-600' }}" style="--chart-width: {{ $row['total'] / $maximum * 100 }}%; --bar-delay: {{ $index * 65 }}ms"></div></div>
                        </li>
                    @empty
                        <li class="border-l-2 border-emerald-300 pl-4 text-sm text-slate-500">No unresolved tickets to distribute.</li>
                    @endforelse
                </ul>
                @if ($id === 'department-workload' && $data['other_departments'] > 0)
                    <p class="mt-4 text-xs text-slate-500">{{ number_format($data['other_departments']) }} more unresolved tickets across other departments.</p>
                @endif
                @if ($id === 'ticket-age' && $data['active'] === 0)
                    <p class="mt-4 text-sm text-emerald-700">The unresolved queue is clear.</p>
                @endif
            </figure>
        @endforeach
    </div>
</section>
