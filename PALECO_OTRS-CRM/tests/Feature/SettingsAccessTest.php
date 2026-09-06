<?php

test('settings pages require sign in', function (string $path) {
    $this->get($path)->assertRedirect();
})->with(['/admin/settings', '/cwd/settings']);

test('settings routes retain their role gates', function (string $name, string $gate) {
    $route = app('router')->getRoutes()->getByName($name);
    expect($route->gatherMiddleware())->toContain('auth', $gate);
})->with([
    ['admin.settings', 'can:access-admin'],
    ['cwd.settings', 'can:access-cwd_officer'],
]);
