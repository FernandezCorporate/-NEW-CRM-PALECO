<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('the application redirects unauthenticated guests from root', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
});

test('seeded users exist in test database', function () {
    expect(User::count())->toBe(4);
});
