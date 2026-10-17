<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed(DatabaseSeeder::class);
});

test('Admin dashboard loads successfully even when activity logs have null causers', function () {
    $admin = User::where('username', 'allenglenn')->first();

    // Create an unauthenticated/failed login activity log where causer is null
    activity('login_failed')
        ->event('login')
        ->withProperties([
            'username' => 'intruder',
            'ip_address' => '127.0.0.1',
        ])
        ->log('Login attempt failed.');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('PALECO Control Center');
    $response->assertSee('intruder');
});

