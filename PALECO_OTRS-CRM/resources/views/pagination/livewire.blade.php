{{-- Pagination for Livewire components: same design as the regular pages, but pages change without a full reload --}}
<div>
    @if ($paginator->hasPages())
        @include('pagination.window', ['paginator' => $paginator, 'live' => true, 'scrollTo' => $scrollTo ?? 'body'])
    @endif
</div>
