<?php

use App\Enums\TicketStatus;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['database.default' => 'auth_test', 'database.connections.auth_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);

    Schema::create('activity_log', function (Blueprint $table) {
        $table->id();
        $table->string('log_name')->nullable();
        $table->text('description');
        $table->string('subject_type')->nullable();
        $table->string('subject_id')->nullable();
        $table->string('causer_type')->nullable();
        $table->string('causer_id')->nullable();
        $table->string('event')->nullable();
        $table->json('attribute_changes')->nullable();
        $table->json('properties')->nullable();
        $table->timestamps();
    });

    Schema::create('account_roles', function (Blueprint $table) {
        $table->id();
        $table->string('role_name');
        $table->string('slug_identifier')->unique();
        $table->timestamps();
    });

    Schema::create('departments', function (Blueprint $table) {
        $table->id();
        $table->string('dept_name');
        $table->string('dept_desc')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('username')->unique();
        $table->string('first_name');
        $table->string('middle_name')->nullable();
        $table->string('last_name');
        $table->string('name_ext')->nullable();
        $table->string('email')->nullable();
        $table->string('contact');
        $table->foreignId('role_id')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->string('password');
        $table->tinyInteger('is_active')->default(1);
        $table->timestamps();
    });

    Schema::create('teams', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('team_name');
        $table->string('team_desc')->nullable();
        $table->time('shift_start')->nullable();
        $table->time('shift_end')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('team_members', function (Blueprint $table) {
        $table->string('user_id', 26);
        $table->string('team_id', 26);
        $table->foreignId('team_role_id')->nullable();
        $table->primary(['user_id', 'team_id']);
        $table->timestamps();
    });

    Schema::create('ticket_categories', function (Blueprint $table) {
        $table->id();
        $table->string('category_name');
        $table->string('category_desc')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('tickets', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_number')->unique();
        $table->string('parent_ticket_id', 26)->nullable();
        $table->string('consumer_id', 26)->nullable();
        $table->string('consumer_contact')->nullable();
        $table->string('complaint_source')->default('walk_in');
        $table->text('complaint_description')->nullable();
        $table->foreignId('category_id')->nullable();
        $table->tinyInteger('other_category')->default(0);
        $table->string('other_category_name')->nullable();
        $table->string('purok')->nullable();
        $table->string('street')->nullable();
        $table->string('barangay');
        $table->string('landmark')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->string('team_id', 26)->nullable();
        $table->string('created_by_id', 26)->nullable();
        $table->string('status')->default('open');
        $table->tinyInteger('is_offline_synced')->default(0);
        $table->timestamp('synced_at')->nullable();
        $table->timestamp('reported_at')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('client_started_at')->nullable();
        $table->timestamp('resolved_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('ticket_assignments', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('team_id', 26);
        $table->string('assigned_by_id', 26);
        $table->text('reason')->nullable();
        $table->timestamp('unassigned_at')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_status_logs', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('changed_by_id', 26)->nullable();
        $table->string('old_status', 30)->nullable();
        $table->string('new_status', 30);
        $table->timestamps();
    });
});

test('supervisor accessing ticket from another department receives 404 Not Found (anti-enumeration)', function () {
    $supRole = AccountRole::create(['id' => 1, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);
    $deptA = Department::create(['id' => 10, 'dept_name' => 'Technical Services Department']);
    $deptB = Department::create(['id' => 20, 'dept_name' => 'Area Operations Department']);

    $supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_tsd',
        'first_name' => 'Mycka',
        'last_name' => 'Supervisor',
        'contact' => '09123456789',
        'password' => bcrypt('password'),
        'role_id' => $supRole->id,
        'department_id' => $deptA->id,
    ]);

    $ticketInDeptB = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-DEPTB-001',
        'barangay' => 'San Pedro',
        'department_id' => $deptB->id,
        'status' => TicketStatus::ASSIGNED->value,
    ]);

    // Attempting to view ticket of another department returns 404 (not 403)
    $response = $this->actingAs($supervisor)->getJson("/api/tickets/{$ticketInDeptB->id}");

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'The requested ticket was not found.',
        ]);
});

test('supervisor accessing ticket from own department receives 200 OK', function () {
    $supRole = AccountRole::create(['id' => 1, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);
    $deptA = Department::create(['id' => 10, 'dept_name' => 'Technical Services Department']);

    $supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_tsd_2',
        'first_name' => 'Mycka',
        'last_name' => 'Supervisor',
        'contact' => '09123456789',
        'password' => bcrypt('password'),
        'role_id' => $supRole->id,
        'department_id' => $deptA->id,
    ]);

    $ticketInDeptA = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-DEPTA-001',
        'barangay' => 'San Pedro',
        'department_id' => $deptA->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $response = $this->actingAs($supervisor)->getJson("/api/tickets/{$ticketInDeptA->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.ticket_number', 'TKT-DEPTA-001');
});

test('field personnel accessing ticket not assigned to their team receives 404 Not Found', function () {
    $fieldRole = AccountRole::create(['id' => 2, 'role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);
    $dept = Department::create(['id' => 10, 'dept_name' => 'TSD']);

    $fieldUser = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'lineman_1',
        'first_name' => 'Juan',
        'last_name' => 'Lineman',
        'contact' => '09123456789',
        'password' => bcrypt('password'),
        'role_id' => $fieldRole->id,
        'department_id' => $dept->id,
    ]);

    $teamUser = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Crew 1',
        'department_id' => $dept->id,
    ]);
    DB::table('team_members')->insert(['user_id' => $fieldUser->id, 'team_id' => $teamUser->id]);

    $otherTeam = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Crew 2',
        'department_id' => $dept->id,
    ]);

    $ticketForOtherTeam = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-OTHER-001',
        'barangay' => 'San Pedro',
        'department_id' => $dept->id,
        'team_id' => $otherTeam->id,
        'status' => TicketStatus::ASSIGNED->value,
    ]);

    $response = $this->actingAs($fieldUser)->getJson("/api/tickets/{$ticketForOtherTeam->id}");

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'The requested ticket was not found.',
        ]);
});

test('supervisor inspecting team belonging to another department receives 404 Not Found', function () {
    $supRole = AccountRole::create(['id' => 1, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);
    $deptA = Department::create(['id' => 10, 'dept_name' => 'TSD']);
    $deptB = Department::create(['id' => 20, 'dept_name' => 'AOD']);

    $supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_dept_a',
        'first_name' => 'Supervisor',
        'last_name' => 'Alpha',
        'contact' => '09123456789',
        'password' => bcrypt('password'),
        'role_id' => $supRole->id,
        'department_id' => $deptA->id,
    ]);

    $teamDeptB = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Bravo Crew',
        'department_id' => $deptB->id,
    ]);

    $response = $this->actingAs($supervisor)->getJson("/api/teams/{$teamDeptB->id}");

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'The requested team was not found.',
        ]);
});

test('unauthenticated request retains 401 Unauthorized', function () {
    $response = $this->getJson('/api/tickets');

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Unauthenticated. A valid Bearer token is required.',
        ]);
});

test('non-supervisor accessing supervisor-only route receives 404 Not Found (route obscured)', function () {
    $fieldRole = AccountRole::create(['id' => 2, 'role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);
    $dept = Department::create(['id' => 10, 'dept_name' => 'TSD']);

    $fieldUser = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'lineman_2',
        'first_name' => 'Pedro',
        'last_name' => 'Lineman',
        'contact' => '09123456789',
        'password' => bcrypt('password'),
        'role_id' => $fieldRole->id,
        'department_id' => $dept->id,
    ]);

    // Field personnel attempting to browse supervisor teams gets 404 Not Found (obscured)
    $response = $this->actingAs($fieldUser)->getJson('/api/teams');

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'The requested resource was not found.',
        ]);
});

test('standard authorization denial without 404 status retains 403 Forbidden', function () {
    $fieldRole = AccountRole::create(['id' => 2, 'role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);
    $user = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'test_user_403',
        'first_name' => 'Test',
        'last_name' => 'User',
        'contact' => '09123456789',
        'password' => bcrypt('password'),
        'role_id' => $fieldRole->id,
    ]);

    // Direct policy evaluation of a standard deny
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-DENY-001',
        'barangay' => 'San Pedro',
    ]);

    $policy = new \App\Policies\TicketPolicy();
    $response = $policy->createChild($user, $ticket);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBeNull(); // Denies with 403 standard HTTP status
});

test('supervisor attempting to archive team from another department receives 404 Not Found', function () {
    $supRole = AccountRole::create(['id' => 1, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);
    $deptA = Department::create(['id' => 10, 'dept_name' => 'TSD']);
    $deptB = Department::create(['id' => 20, 'dept_name' => 'AOD']);

    $supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_dept_a_archive',
        'first_name' => 'Supervisor',
        'last_name' => 'Alpha',
        'contact' => '09123456789',
        'password' => bcrypt('password'),
        'role_id' => $supRole->id,
        'department_id' => $deptA->id,
    ]);

    $teamDeptB = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Bravo Crew Archive Target',
        'department_id' => $deptB->id,
    ]);

    $response = $this->actingAs($supervisor)->deleteJson("/api/teams/{$teamDeptB->id}/archive");

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'The requested team was not found.',
        ]);
});
