<?php

use App\Enums\TicketStatus;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'allenglenn')->first();
    $this->cwdOfficer = User::where('username', 'alliah')->first();
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->personnel = User::where('username', 'ralph')->first();
});

test('admin can view paginated users list with search and role filters', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.users'));

    $response->assertOk();
    $response->assertViewIs('admin.pages.userManagement');
    $response->assertViewHas('users');
});

test('admin can filter users list by role', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.users', ['filter' => 'cwd_officer']));

    $response->assertOk();
    $users = $response->viewData('users');
    expect($users->pluck('username'))->toContain('alliah')
        ->and($users->pluck('username'))->not->toContain('mycka');
});

test('admin can view user detailed profile', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.users.show', ['user' => $this->cwdOfficer]));

    $response->assertOk();
    $response->assertViewIs('admin.pages.userDetails');
    $response->assertViewHas('user');
});

test('admin can view user creation form with roles and departments', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.users.createForm'));

    $response->assertOk();
    $response->assertViewIs('admin.forms.userForm');
    $response->assertViewHas(['roles', 'depts']);
});

test('admin can create a new user account with encrypted pii', function () {
    $role = AccountRole::where('slug_identifier', 'cwd_officer')->first();

    $payload = [
        'username' => 'newofficer',
        'first_name' => 'John',
        'middle_name' => 'Public',
        'last_name' => 'Citizen',
        'name_ext' => null,
        'email' => 'john.citizen@paleco.com',
        'contact' => '09123456780',
        'role_id' => $role->id,
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ];

    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), $payload);

    $response->assertRedirect(route('admin.users'));
    $response->assertSessionHas('success');

    $created = User::where('username', 'newofficer')->first();
    expect($created)->not->toBeNull()
        ->and(strtolower($created->first_name))->toBe('john')
        ->and(strtolower($created->last_name))->toBe('citizen')
        ->and($created->is_active)->toBeTrue();
});

test('admin can view user edit form', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.users.editForm', ['user' => $this->cwdOfficer]));

    $response->assertOk();
    $response->assertViewIs('admin.forms.userForm');
});

test('admin can update user profile details', function () {
    $payload = [
        'original_updated_at' => $this->cwdOfficer->updated_at->toIso8601String(),
        'username' => $this->cwdOfficer->username,
        'first_name' => 'Alliah Updated',
        'middle_name' => 'Mid',
        'last_name' => 'Officer',
        'name_ext' => null,
        'email' => 'alliah.updated@paleco.com',
        'contact' => '09998887777',
        'department_id' => null,
    ];

    $response = $this->actingAs($this->admin)
        ->put(route('admin.users.update', ['user' => $this->cwdOfficer]), $payload);

    $response->assertRedirect(route('admin.users'));
    $response->assertSessionHas('success');

    $this->cwdOfficer->refresh();
    expect(strtolower($this->cwdOfficer->first_name))->toBe('alliah updated')
        ->and($this->cwdOfficer->contact)->toBe('09998887777');
});

test('admin can deactivate and reactivate a user account', function () {
    // 1. Deactivate
    $responseDeact = $this->actingAs($this->admin)
        ->patch(route('admin.users.deactivate', ['user' => $this->cwdOfficer]));

    $responseDeact->assertRedirect(route('admin.users'));
    $responseDeact->assertSessionHas('success');
    expect($this->cwdOfficer->fresh()->is_active)->toBeFalse();

    // 2. Reactivate
    $responseReact = $this->actingAs($this->admin)
        ->patch(route('admin.users.reactivate', ['user' => $this->cwdOfficer]));

    $responseReact->assertRedirect(route('admin.users'));
    $responseReact->assertSessionHas('success');
    expect($this->cwdOfficer->fresh()->is_active)->toBeTrue();
});

test('deactivating field personnel is blocked if their crew has active tickets', function () {
    $team = Team::first();
    if (! $team->members->contains($this->personnel->id)) {
        $team->members()->attach($this->personnel->id, ['team_role_id' => 1]);
    }

    $dept = Department::first();
    $cat = TicketCategory::first();

    Ticket::factory()->inProgress($team)->create([
        'department_id' => $dept->id,
        'category_id' => $cat->id,
    ]);

    // Deactivate confirm prompt redirects back with error
    $response = $this->actingAs($this->admin)
        ->get(route('admin.users.deactivateConfirm', ['user' => $this->personnel]));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($this->personnel->fresh()->is_active)->toBeTrue();
});

test('non-admin users cannot access admin user management routes', function () {
    $response = $this->actingAs($this->cwdOfficer)->get(route('admin.users'));

    // Admin gate blocks with 404
    $response->assertNotFound();
});
