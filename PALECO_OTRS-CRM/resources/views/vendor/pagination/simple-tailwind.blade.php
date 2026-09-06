@if ($paginator->hasPages())
<nav class="system-pagination justify-end" role="navigation" aria-label="Pagination navigation">
    <div class="pagination-controls">
        @if ($paginator->onFirstPage()) <span class="pagination-step is-disabled" aria-disabled="true">Previous</span>
        @else <a class="pagination-step" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a> @endif
        @if ($paginator->hasMorePages()) <a class="pagination-step" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else <span class="pagination-step is-disabled" aria-disabled="true">Next</span> @endif
    </div>
</nav>
@endif
