{{-- Laravel's default pagination view, restyled: the shared design lives in resources/views/pagination/window.blade.php --}}
@if ($paginator->hasPages())
    @include('pagination.window', ['paginator' => $paginator, 'live' => false])
@endif
