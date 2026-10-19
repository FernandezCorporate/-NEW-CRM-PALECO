<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('admin can view system monitoring dashboard', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.monitoring.index'));

    $response->assertStatus(200);
    $response->assertViewIs('admin.pages.monitoring');
    $response->assertViewHasAll(['logs', 'categories', 'eventTypes']);
});

test('non-admin users cannot access system monitoring dashboard', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $supervisor = User::factory()->supervisor()->create();
    $fieldPersonnel = User::factory()->fieldPersonnel()->create();

    $this->actingAs($cwdOfficer)->get(route('admin.monitoring.index'))
        ->assertStatus(404);

    $this->actingAs($supervisor)->get(route('admin.monitoring.index'))
        ->assertStatus(404);

    $this->actingAs($fieldPersonnel)->get(route('admin.monitoring.index'))
        ->assertStatus(404);
});

test('unauthenticated guests are redirected to login', function () {
    $this->get(route('admin.monitoring.index'))
        ->assertRedirect(route('login'));
});

test('admin can filter monitoring logs by search, category, and event type', function () {
    $admin = User::factory()->admin()->create();

    activity('Tickets')
        ->event('created')
        ->log('Consumer service ticket created');

    activity('Users')
        ->event('updated')
        ->log('User account profile modified');

    // Filter by search
    $searchResponse = $this->actingAs($admin)->get(route('admin.monitoring.index', ['search' => 'Consumer service']));
    $searchResponse->assertStatus(200);
    $logs = $searchResponse->viewData('logs');
    expect($logs->total())->toBeGreaterThanOrEqual(1);

    // Filter by category
    $categoryResponse = $this->actingAs($admin)->get(route('admin.monitoring.index', ['category' => 'Tickets']));
    $categoryResponse->assertStatus(200);

    // Filter by event type
    $eventResponse = $this->actingAs($admin)->get(route('admin.monitoring.index', ['event_type' => 'create']));
    $eventResponse->assertStatus(200);
});

test('admin can view forensic activity details for logged user action', function () {
    $admin = User::factory()->admin()->create();

    $activity = activity('Tickets')
        ->causedBy($admin)
        ->event('created')
        ->withProperties(['ip_address' => '127.0.0.1', 'user_agent' => 'PHPUnit Browser'])
        ->log('Admin created a priority service ticket');

    $response = $this->actingAs($admin)->get(route('admin.monitoring.show', $activity));

    $response->assertStatus(200);
    $response->assertViewIs('admin.pages.activityLogDetails');
    $response->assertViewHas('activity');
});

test('admin can view activity details when causer is null or system-generated', function () {
    $admin = User::factory()->admin()->create();

    $activity = activity('System')
        ->event('system_event')
        ->withProperties(['full_name' => 'Automated Scheduler', 'ip_address' => '127.0.0.1'])
        ->log('Automated maintenance task executed');

    $response = $this->actingAs($admin)->get(route('admin.monitoring.show', $activity));

    $response->assertStatus(200);
    $response->assertViewIs('admin.pages.activityLogDetails');
    $response->assertViewHas('activity');
});

