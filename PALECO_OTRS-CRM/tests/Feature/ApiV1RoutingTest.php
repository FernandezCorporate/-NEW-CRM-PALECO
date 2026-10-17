<?php

test('unversioned api routes return 404 with v1 migration hint', function () {
    $response = $this->postJson('/api/login', []);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'The requested endpoint was not found. Please verify the API base URL is versioned at /api/v1/.',
        ]);
});

test('api v1 login route is available and validates payload', function () {
    $response = $this->postJson('/api/v1/login', []);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'success',
            'message',
            'errors',
        ]);
});

test('api v1 protected endpoints require sanctum bearer token', function () {
    $response = $this->getJson('/api/v1/user/profile');

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Unauthenticated. A valid Bearer token is required.',
        ]);
});

