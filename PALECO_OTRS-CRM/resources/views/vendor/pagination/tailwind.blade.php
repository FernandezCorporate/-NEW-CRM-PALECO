@if ($paginator->hasPages())
<nav class="system-pagination" role="navigation" aria-label="Pagination navigation">
    <p class="pagination-summary">Showing <strong>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}</strong> of <strong>{{ $paginator->total() }}</strong> results</p>
    <div class="pagination-controls">
        @if ($paginator->onFirstPage())
            <span class="pagination-step is-disabled" aria-disabled="true"><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m12.5 15-5-5 5-5" /></svg><span>Previous</span></span>
        @else
            <a class="pagination-step" href="{{ $paginator->previousPageUrl() }}" rel="prev"><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m12.5 15-5-5 5-5" /></svg><span>Previous</span></a>
        @endif
        <span class="pagination-pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-ellipsis" aria-hidden="true">{{ $element }}</span>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-page is-current" aria-current="page"><span class="sr-only">Current page, </span>{{ $page }}</span>
                        @else
                            <a class="pagination-page" href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </span>
        @if ($paginator->hasMorePages())
            <a class="pagination-step" href="{{ $paginator->nextPageUrl() }}" rel="next"><span>Next</span><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m7.5 5 5 5-5 5" /></svg></a>
        @else
            <span class="pagination-step is-disabled" aria-disabled="true"><span>Next</span><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m7.5 5 5 5-5 5" /></svg></span>
        @endif
    </div>
</nav>
@endif
