<?php

use Illuminate\Pagination\LengthAwarePaginator;

test('shared pagination renders result context and accessible states', function () {
    $paginator = new LengthAwarePaginator(range(11, 20), 35, 10, 2, [
        'path' => '/records',
    ]);

    $html = $paginator->links()->render();

    expect($html)
        ->toContain('system-pagination')
        ->toContain('Showing <strong>11–20</strong> of <strong>35</strong> results')
        ->toContain('aria-current="page"')
        ->toContain('rel="prev"')
        ->toContain('rel="next"');
});

test('shared pagination marks unavailable navigation as disabled', function () {
    $paginator = new LengthAwarePaginator(range(1, 10), 11, 10, 1, [
        'path' => '/records',
    ]);

    expect($paginator->links()->render())
        ->toContain('pagination-step is-disabled')
        ->toContain('aria-disabled="true"');
});
