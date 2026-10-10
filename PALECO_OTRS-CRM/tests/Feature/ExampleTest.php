<?php

test('the application redirects unauthenticated guests from root', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
});
