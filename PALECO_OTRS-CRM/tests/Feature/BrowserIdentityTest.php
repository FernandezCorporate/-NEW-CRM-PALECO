<?php

test('portal uses the PALECO browser identity', function () {
    $this->get('/portal')
        ->assertOk()
        ->assertSee('<title>Select Portal | PALECO CRM-CWD</title>', false)
        ->assertSee('rel="icon" type="image/png"', false)
        ->assertSee('/images/paleco-logo.png', false);
});

test('role login tabs identify the selected portal', function (string $role, string $title) {
    $this->get('/login/' . $role)
        ->assertOk()
        ->assertSee('<title>' . $title . ' Sign In | PALECO</title>', false)
        ->assertSee('rel="icon" type="image/png"', false);
})->with([
    ['admin', 'Administrator'],
    ['cwd_officer', 'CWD Officer'],
]);
