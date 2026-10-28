<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
});

test('the application redirects unauthenticated guests from root', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
});

test('seeded users exist in test database', function () {
    expect(User::count())->toBe(4);
});
