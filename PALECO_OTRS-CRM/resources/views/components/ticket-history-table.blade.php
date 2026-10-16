@props([
    'id',
    'title',
    'description',
    'count',
    'columns',
    'backRef' => '#ticket-history',
    'backLabel' => 'Back to history ↑',
    'caption' => null,
    'tableClass' => '',
])

<section id="{{ $id }}" class="ticket-history-section" data-tab-panel="{{ $id }}" role="tabpanel" aria-labelledby="{{ $id }}-heading">
    <header class="ticket-history-heading">
        <div>
            <h3 id="{{ $id }}-heading">{{ $title }} <span class="ticket-history-count">{{ number_format($count) }}</span></h3>
            <p>{{ $description }}</p>
        </div>
        <a class="ticket-history-back" href="{{ $backRef }}">{{ $backLabel }}</a>
    </header>
    <p class="ticket-history-scroll-hint">Scroll horizontally if more columns are available.</p>
    <div class="overflow-x-auto ticket-history-scroll" tabindex="0" role="region" aria-labelledby="{{ $id }}-heading">
        <table class="ticket-history-table {{ $tableClass }}">
            <caption class="sr-only">{{ $caption ?? ($title . ' for this record') }}</caption>
            <thead>
                <tr>
                    @foreach($columns as $column)
                        @php
                            $colName = is_array($column) ? ($column['name'] ?? '') : $column;
                            $colClass = is_array($column) ? ($column['class'] ?? '') : (in_array(strtolower($colName), ['actions', 'action', 'status', 'members']) ? 'text-center' : '');
                        @endphp
                        <th scope="col" @if($colClass) class="{{ $colClass }}" @endif>{{ $colName }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>{{ $slot }}</tbody>
        </table>
    </div>
    @isset($footer)
        <div class="px-6 py-4 border-t border-slate-100 bg-white rounded-b-xl">
            {{ $footer }}
        </div>
    @endisset
</section>
