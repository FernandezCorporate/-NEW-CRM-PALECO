{{--
    Shared pagination design: First · up to 3 pages before · current page · up to 3 pages after · Last.
    Works with Laravel's default length-aware paginator. $live = true renders Livewire buttons instead of links.
--}}
@php
    $live = $live ?? false;
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $from = max(1, $current - 3);
    $to = min($last, $current + 3);
    $pageName = $paginator->getPageName();
    $scrollTo = $scrollTo ?? 'body';
    $scroll = $scrollTo !== false ? "(\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()" : '';
@endphp

<nav class="system-pagination" role="navigation" aria-label="Pagination navigation">
    <p class="pagination-summary">Showing <strong>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}</strong> of <strong>{{ $paginator->total() }}</strong> results</p>

    <div class="pagination-controls">
        {{-- First page --}}
        @if ($paginator->onFirstPage())
            <span class="pagination-step is-disabled" aria-disabled="true"><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m10 15-5-5 5-5M15 15l-5-5 5-5" /></svg><span>First</span></span>
        @elseif ($live)
            <button type="button" class="pagination-step" wire:click="gotoPage(1, '{{ $pageName }}')" x-on:click="{{ $scroll }}" wire:loading.attr="disabled" aria-label="Go to the first page"><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m10 15-5-5 5-5M15 15l-5-5 5-5" /></svg><span>First</span></button>
        @else
            <a class="pagination-step" href="{{ $paginator->url(1) }}" aria-label="Go to the first page"><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m10 15-5-5 5-5M15 15l-5-5 5-5" /></svg><span>First</span></a>
        @endif

        {{-- Up to 3 pages on each side of the current page (phones show 1 on each side; see data-distance in the CSS) --}}
        <span class="pagination-pages">
            @for ($page = $from; $page <= $to; $page++)
                @php($distance = abs($page - $current))
                @if ($page === $current)
                    <span class="pagination-page is-current" aria-current="page"><span class="sr-only">Current page, </span>{{ $page }}</span>
                @elseif ($live)
                    <button type="button" class="pagination-page" data-distance="{{ $distance }}" wire:key="paginator-{{ $pageName }}-page-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" x-on:click="{{ $scroll }}" wire:loading.attr="disabled" aria-label="Go to page {{ $page }}">{{ $page }}</button>
                @else
                    <a class="pagination-page" data-distance="{{ $distance }}" href="{{ $paginator->url($page) }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                @endif
            @endfor
        </span>

        {{-- Last page --}}
        @if ($current >= $last)
            <span class="pagination-step is-disabled" aria-disabled="true"><span>Last</span><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m5 5 5 5-5 5M10 5l5 5-5 5" /></svg></span>
        @elseif ($live)
            <button type="button" class="pagination-step" wire:click="gotoPage({{ $last }}, '{{ $pageName }}')" x-on:click="{{ $scroll }}" wire:loading.attr="disabled" aria-label="Go to the last page, page {{ $last }}"><span>Last</span><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m5 5 5 5-5 5M10 5l5 5-5 5" /></svg></button>
        @else
            <a class="pagination-step" href="{{ $paginator->url($last) }}" aria-label="Go to the last page, page {{ $last }}"><span>Last</span><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m5 5 5 5-5 5M10 5l5 5-5 5" /></svg></a>
        @endif
    </div>
</nav>
