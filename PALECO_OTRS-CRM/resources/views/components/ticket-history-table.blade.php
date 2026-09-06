@props(['id', 'title', 'description', 'count', 'columns'])

<section id="{{ $id }}" class="ticket-history-section" aria-labelledby="{{ $id }}-heading">
    <header class="ticket-history-heading">
        <div>
            <h3 id="{{ $id }}-heading">{{ $title }} <span class="ticket-history-count">{{ number_format($count) }}</span></h3>
            <p>{{ $description }}</p>
        </div>
        <a class="ticket-history-back" href="#ticket-history">Back to history ↑</a>
    </header>
    <p class="ticket-history-scroll-hint">Scroll horizontally if more columns are available.</p>
    <div class="overflow-x-auto ticket-history-scroll" tabindex="0" role="region" aria-labelledby="{{ $id }}-heading">
        <table class="ticket-history-table">
            <caption class="sr-only">{{ $title }} for this ticket</caption>
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th scope="col">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>{{ $slot }}</tbody>
        </table>
    </div>
</section>
