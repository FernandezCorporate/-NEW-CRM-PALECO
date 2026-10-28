<?php

use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
});

test('admin can view department management index', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.departments'));

    $response->assertStatus(200);
    $response->assertViewIs('admin.pages.departmentManagement');
    $response->assertViewHas('departments');
});

test('non-admin users cannot access department management', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $supervisor = User::factory()->supervisor()->create();
    $fieldPersonnel = User::factory()->fieldPersonnel()->create();

    $this->actingAs($cwdOfficer)->get(route('admin.departments'))
        ->assertStatus(404);

    $this->actingAs($supervisor)->get(route('admin.departments'))
        ->assertStatus(404);

    $this->actingAs($fieldPersonnel)->get(route('admin.departments'))
        ->assertStatus(404);
});

test('admin can view department details and forms', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::first();

    $this->actingAs($admin)->get(route('admin.departments.show', $dept))
        ->assertStatus(200)
        ->assertViewIs('admin.pages.departmentDetails')
        ->assertViewHas('dept');

    $this->actingAs($admin)->get(route('admin.departments.createForm'))
        ->assertStatus(200)
        ->assertViewIs('admin.forms.departmentForm');

    $this->actingAs($admin)->get(route('admin.departments.editForm', $dept))
        ->assertStatus(200)
        ->assertViewIs('admin.forms.departmentForm');
});

test('attempting to edit an archived department redirects with error', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    $dept->delete();

    $response = $this->actingAs($admin)->get(route('admin.departments.editForm', $dept));

    $response->assertRedirect(route('admin.departments'));
    $response->assertSessionHas('error', 'The department you are trying to edit has been archived by another administrator.');
});

test('admin can create a new department', function () {
    $admin = User::factory()->admin()->create();

    $payload = [
        'dept_name' => 'Renewable Energy Operations',
        'dept_desc' => 'Department overseeing solar and microgrid installations.',
    ];

    $response = $this->actingAs($admin)->post(route('admin.departments.store'), $payload);

    $response->assertRedirect(route('admin.departments'));
    $response->assertSessionHas('success', 'Department created successfully.');

    $this->assertDatabaseHas('departments', [
        'dept_name' => 'Renewable Energy Operations',
    ]);
});

test('department creation fails when name is missing or duplicated', function () {
    $admin = User::factory()->admin()->create();
    $existing = Department::first();

    // Duplicate
    $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
        'dept_name' => $existing->dept_name,
    ]);
    $response->assertSessionHasErrors(['dept_name']);

    // Missing
    $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
        'dept_name' => '',
    ]);
    $response->assertSessionHasErrors(['dept_name']);
});

test('admin can update a department with concurrency checking', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create([
        'dept_name' => 'Initial Department Name',
    ]);

    $payload = [
        'dept_name' => 'Revised Department Name',
        'dept_desc' => 'Updated department scope description.',
        'original_updated_at' => $dept->updated_at->toIso8601String(),
    ];

    $response = $this->actingAs($admin)->put(route('admin.departments.update', $dept), $payload);

    $response->assertRedirect(route('admin.departments'));
    $response->assertSessionHas('success', 'Department updated successfully.');

    expect($dept->fresh()->dept_name)->toBe('Revised Department Name');
});

test('department update fails on concurrency timestamp mismatch', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();

    $staleTimestamp = $dept->updated_at->subMinutes(10)->toIso8601String();

    $payload = [
        'dept_name' => 'Stale Collision Dept',
        'original_updated_at' => $staleTimestamp,
    ];

    $response = $this->actingAs($admin)->put(route('admin.departments.update', $dept), $payload);

    $response->assertSessionHas('error');
    expect($dept->fresh()->dept_name)->not->toBe('Stale Collision Dept');
});

test('admin can archive, restore, and force-delete an empty department', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();

    // Confirm prompt
    $this->actingAs($admin)->get(route('admin.departments.deleteConfirm', $dept))
        ->assertStatus(200)
        ->assertViewIs('admin.prompts.departmentDeleteConfirm');

    // Archive
    $archiveResponse = $this->actingAs($admin)->delete(route('admin.departments.archive', $dept));
    $archiveResponse->assertRedirect(route('admin.departments'));
    $archiveResponse->assertSessionHas('success', 'Department archived successfully.');
    expect($dept->fresh()->trashed())->toBeTrue();

    // Restore
    $restoreResponse = $this->actingAs($admin)->patch(route('admin.departments.restore', $dept));
    $restoreResponse->assertRedirect(route('admin.departments'));
    $restoreResponse->assertSessionHas('success', 'Department restored successfully.');
    expect($dept->fresh()->trashed())->toBeFalse();

    // Re-archive and permanently force delete
    $this->actingAs($admin)->delete(route('admin.departments.archive', $dept));
    $destroyResponse = $this->actingAs($admin)->delete(route('admin.departments.destroy', $dept));
    $destroyResponse->assertRedirect(route('admin.departments'));
    $destroyResponse->assertSessionHas('success', 'Department permanently deleted.');
    $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
});

test('archiving department is blocked if department contains active teams or supervisors or tickets', function () {
    $admin = User::factory()->admin()->create();
    $deptWithTeam = Department::factory()->create();
    Team::factory()->create(['department_id' => $deptWithTeam->id]);

    $response = $this->actingAs($admin)->delete(route('admin.departments.archive', $deptWithTeam));

    $response->assertRedirect(route('admin.departments'));
    $response->assertSessionHas('error', 'Cannot archive this department. It contains active teams, personnel, or active service tickets.');
    expect($deptWithTeam->fresh()->trashed())->toBeFalse();
});
