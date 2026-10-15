<?php

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Consumer;
use App\Models\TeamRole;
use App\Models\TicketAccomplishment;
use App\Models\TicketAssignment;
use App\Models\TicketEndorsement;
use App\Models\TicketRemark;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['database.default' => 'smart_nav_test', 'database.connections.smart_nav_test' => [
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

    Schema::create('personal_access_tokens', function (Blueprint $table) {
        $table->id();
        $table->ulidMorphs('tokenable');
        $table->text('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable()->index();
        $table->timestamps();
    });

    Schema::create('account_roles', function (Blueprint $table) {
        $table->id();
        $table->string('role_name');
        $table->string('slug_identifier')->unique();
        $table->timestamps();
    });

    Schema::create('team_roles', function (Blueprint $table) {
        $table->id();
        $table->string('role_name');
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
        $table->foreignId('role_id');
        $table->foreignId('department_id')->nullable();
        $table->string('password');
        $table->boolean('is_active')->default(true);
        $table->rememberToken();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('teams', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('team_name');
        $table->foreignId('department_id');
        $table->time('shift_start')->default('08:00:00');
        $table->time('shift_end')->default('17:00:00');
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('team_members', function (Blueprint $table) {
        $table->id();
        $table->string('team_id', 26);
        $table->string('user_id', 26);
        $table->foreignId('team_role_id')->nullable();
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
        $table->string('complaint_source');
        $table->foreignId('category_id')->nullable();
        $table->boolean('other_category')->default(false);
        $table->string('other_category_name')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->string('team_id', 26)->nullable();
        $table->string('status');
        $table->text('complaint_description');
        $table->string('purok')->nullable();
        $table->string('street')->nullable();
        $table->string('barangay');
        $table->string('landmark')->nullable();
        $table->string('consumer_contact')->nullable();
        $table->string('consumer_id', 26)->nullable();
        $table->string('created_by_id', 26)->nullable();
        $table->timestamp('reported_at')->useCurrent();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('ticket_status_logs', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('old_status')->nullable();
        $table->string('new_status');
        $table->string('changed_by_id', 26)->nullable();
        $table->timestamps();
    });

    Schema::create('consumers', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('acct_no')->unique();
        $table->string('acct_code')->unique();
        $table->string('name');
        $table->string('address');
        $table->string('status')->nullable();
        $table->string('meter_serial')->nullable();
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

    Schema::create('ticket_endorsements', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_id', 26);
        $table->string('created_by_id', 26);
        $table->foreignId('suggested_department_id')->nullable();
        $table->text('reason');
        $table->string('status', 30)->default('pending');
        $table->string('pre_endorsement_status', 30)->default('open');
        $table->text('rejection_reason')->nullable();
        $table->string('reviewed_by_id', 26)->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_accomplishments', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('accomplished_by_id', 26);
        $table->text('remarks');
        $table->timestamp('accomplished_at');
        $table->string('consumer_name')->nullable();
        $table->string('signature_path')->nullable();
        $table->string('status', 30)->default('pending');
        $table->string('approved_by_id', 26)->nullable();
        $table->string('rejected_by_id', 26)->nullable();
        $table->text('rejection_reason')->nullable();
        $table->timestamps();
    });

    Schema::create('accomplishment_photos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('accomplishment_id');
        $table->string('file_path');
        $table->string('file_name')->nullable();
        $table->unsignedInteger('file_size')->nullable();
        $table->string('mime_type', 50)->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_remarks', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_id', 26);
        $table->string('user_id', 26);
        $table->text('body');
        $table->boolean('is_internal')->default(false);
        $table->timestamps();
    });

    $this->roleAdmin = AccountRole::create(['role_name' => 'Administrator', 'slug_identifier' => 'admin']);
    $this->roleCwd = AccountRole::create(['role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);
    $this->roleSupervisor = AccountRole::create(['role_name' => 'Department Supervisor', 'slug_identifier' => 'supervisor']);
    $this->memberRole = TeamRole::create(['role_name' => 'Crew Member']);

    $this->dept = Department::create(['dept_name' => 'Technical Services', 'dept_desc' => 'Technical operations']);

    $this->adminUser = User::create([
        'id' => '01JADMIN000000000000000001',
        'username' => 'sysadmin',
        'first_name' => 'System',
        'last_name' => 'Admin',
        'contact' => '09123456789',
        'role_id' => $this->roleAdmin->id,
        'department_id' => $this->dept->id,
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $this->cwdUser = User::create([
        'id' => '01JCWD00000000000000000001',
        'username' => 'cwdofficer',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'contact' => '09123456788',
        'role_id' => $this->roleCwd->id,
        'department_id' => null,
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $this->category = TicketCategory::create(['category_name' => 'Power Outage', 'category_desc' => 'Blackout']);

    $this->team = Team::create([
        'team_name' => 'Line Crew Alpha',
        'department_id' => $this->dept->id,
        'shift_start' => '08:00:00',
        'shift_end' => '17:00:00',
    ]);
});

test('CWD ticket creation form preserves dashboard source and points cancel to dashboard', function () {
    Gate::define('ticketForm', fn () => true);

    $response = $this->actingAs($this->cwdUser)->get(route('cwd.tickets.createForm', ['source' => 'dashboard']));

    $response->assertOk();
    $response->assertSee('<input type="hidden" name="source" value="dashboard">', false);
    $response->assertSee(route('cwd.dashboard'));
});

test('CWD ticket creation without source points cancel to ticket list', function () {
    Gate::define('ticketForm', fn () => true);

    $response = $this->actingAs($this->cwdUser)->get(route('cwd.tickets.createForm'));

    $response->assertOk();
    $response->assertDontSee('<input type="hidden" name="source"', false);
    $response->assertSee(route('cwd.tickets'));
});

test('storing CWD ticket with source dashboard redirects to dashboard with flash success', function () {
    Gate::define('create', fn () => true);

    $payload = [
        'consumer_contact' => '09123456789',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'purok' => 'Purok 1',
        'street' => 'Rizal Avenue',
        'barangay' => 'San Jose',
        'complaint_description' => 'Blown transformer in street corner.',
        'source' => 'dashboard',
    ];

    $response = $this->actingAs($this->cwdUser)->post(route('cwd.tickets.store'), $payload);

    $response->assertRedirect(route('cwd.dashboard'));
    $response->assertSessionHas('success');
    expect(Ticket::count())->toBe(1);
});

test('storing CWD ticket without source redirects to ticket list', function () {
    Gate::define('create', fn () => true);

    $payload = [
        'consumer_contact' => '09123456789',
        'complaint_source' => ComplaintSources::WALK_IN->value,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'purok' => 'Purok 2',
        'street' => 'National Highway',
        'barangay' => 'San Pedro',
        'complaint_description' => 'Low voltage reported.',
    ];

    $response = $this->actingAs($this->cwdUser)->post(route('cwd.tickets.store'), $payload);

    $response->assertRedirect(route('cwd.tickets'));
    $response->assertSessionHas('success');
    expect(Ticket::count())->toBe(1);
});

test('updating user with source details redirects back to user details', function () {
    Gate::define('update', fn () => true);

    $targetUser = User::create([
        'id' => '01JTGT00000000000000000001',
        'username' => 'targetuser',
        'first_name' => 'Original',
        'last_name' => 'Name',
        'contact' => '09123456780',
        'role_id' => $this->roleCwd->id,
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $payload = [
        'original_updated_at' => (string) $targetUser->updated_at,
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'contact' => '09123456780',
        'username' => 'targetuser',
        'source' => 'details',
    ];

    $response = $this->actingAs($this->adminUser)->put(route('admin.users.update', $targetUser), $payload);

    $response->assertRedirect(route('admin.users.show', $targetUser));
    expect($targetUser->fresh()->first_name)->toBe('updated');
});

test('updating user without source redirects to users management index', function () {
    Gate::define('update', fn () => true);

    $targetUser = User::create([
        'id' => '01JTGT00000000000000000002',
        'username' => 'targetuser2',
        'first_name' => 'Original',
        'last_name' => 'User',
        'contact' => '09123456781',
        'role_id' => $this->roleCwd->id,
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $payload = [
        'original_updated_at' => (string) $targetUser->updated_at,
        'first_name' => 'Modified',
        'last_name' => 'User',
        'contact' => '09123456781',
        'username' => 'targetuser2',
    ];

    $response = $this->actingAs($this->adminUser)->put(route('admin.users.update', $targetUser), $payload);

    $response->assertRedirect(route('admin.users'));
});

test('user deactivate and reactivate with source details redirect back to user details', function () {
    Gate::define('deactivate', fn () => true);
    Gate::define('reactivate', fn () => true);

    $targetUser = User::create([
        'id' => '01JTGT00000000000000000003',
        'username' => 'toggleuser',
        'first_name' => 'Toggle',
        'last_name' => 'Account',
        'contact' => '09123456782',
        'role_id' => $this->roleCwd->id,
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);

    // Deactivate with source=details
    $responseDeact = $this->actingAs($this->adminUser)->patch(route('admin.users.deactivate', $targetUser), [
        'source' => 'details',
    ]);
    $responseDeact->assertRedirect(route('admin.users.show', $targetUser));
    expect($targetUser->fresh()->is_active)->toBeFalse();

    // Reactivate with source=details
    $responseReact = $this->actingAs($this->adminUser)->patch(route('admin.users.reactivate', $targetUser), [
        'source' => 'details',
    ]);
    $responseReact->assertRedirect(route('admin.users.show', $targetUser));
    expect($targetUser->fresh()->is_active)->toBeTrue();
});

test('updating team with source department redirects back to department details', function () {
    Gate::define('update', fn () => true);

    $payload = [
        'original_updated_at' => (string) $this->team->updated_at,
        'team_name' => 'Line Crew Alpha Modified',
        'department_id' => $this->dept->id,
        'shift_start' => '09:00',
        'shift_end' => '18:00',
        'source' => 'department',
        'dept' => (string) $this->dept->id,
    ];

    $response = $this->actingAs($this->adminUser)->put(route('admin.teams.update', $this->team), $payload);

    $response->assertRedirect(route('admin.departments.show', ['dept' => $this->dept->id]));
    expect($this->team->fresh()->team_name)->toBe('Line Crew Alpha Modified');
});

test('updating team with source details redirects back to team details', function () {
    Gate::define('update', fn () => true);

    $payload = [
        'original_updated_at' => (string) $this->team->updated_at,
        'team_name' => 'Line Crew Alpha Renovated',
        'department_id' => $this->dept->id,
        'shift_start' => '07:00',
        'shift_end' => '16:00',
        'source' => 'details',
    ];

    $response = $this->actingAs($this->adminUser)->put(route('admin.teams.update', $this->team), $payload);

    $response->assertRedirect(route('admin.teams.show', $this->team));
    expect($this->team->fresh()->team_name)->toBe('Line Crew Alpha Renovated');
});

test('updating department with source details redirects back to department details', function () {
    Gate::define('update', fn () => true);

    $payload = [
        'original_updated_at' => (string) $this->dept->updated_at,
        'dept_name' => 'Technical Services Unit',
        'dept_desc' => 'Updated desc',
        'source' => 'details',
    ];

    $response = $this->actingAs($this->adminUser)->put(route('admin.departments.update', $this->dept), $payload);

    $response->assertRedirect(route('admin.departments.show', ['dept' => $this->dept->id]));
    expect($this->dept->fresh()->dept_name)->toBe('Technical Services Unit');
});

test('updating ticket category with source details redirects back to category details', function () {
    Gate::define('update', fn () => true);

    $payload = [
        'original_updated_at' => (string) $this->category->updated_at,
        'category_name' => 'Power Outage Major',
        'category_desc' => 'Updated category description',
        'source' => 'details',
    ];

    $response = $this->actingAs($this->adminUser)->put(route('admin.ticketCategories.update', $this->category), $payload);

    $response->assertRedirect(route('admin.ticketCategories.show', $this->category));
    expect($this->category->fresh()->category_name)->toBe('Power Outage Major');
});

test('department details view renders supervisor and team links with source=department and dept id', function () {
    Gate::define('view', fn () => true);

    $supervisor = User::create([
        'id' => '01JSUPER000000000000000001',
        'username' => 'techsupervisor',
        'first_name' => 'Tech',
        'last_name' => 'Supervisor',
        'contact' => '09123456783',
        'role_id' => $this->roleSupervisor->id,
        'department_id' => $this->dept->id,
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('admin.departments.show', ['dept' => $this->dept->id]));

    $response->assertOk();
    $response->assertSee(route('admin.users.show', ['user' => $supervisor, 'source' => 'department', 'dept' => $this->dept->id]));
    $response->assertSee(route('admin.teams.show', ['team' => $this->team, 'source' => 'department', 'dept' => $this->dept->id]));
});

test('user details view dynamically renders Back to Department link when source is department', function () {
    Gate::define('view', fn () => true);
    Gate::define('update', fn () => true);

    $response = $this->actingAs($this->adminUser)->get(route('admin.users.show', [
        'user' => $this->adminUser,
        'source' => 'department',
        'dept' => $this->dept->id,
    ]));

    $response->assertOk();
    $response->assertSee('Back to Department');
    $response->assertSee(route('admin.departments.show', ['dept' => $this->dept->id]));
});

test('team details view dynamically renders Back to Department link when source is department', function () {
    Gate::define('view', fn () => true);

    $response = $this->actingAs($this->adminUser)->get(route('admin.teams.show', [
        'team' => $this->team,
        'source' => 'department',
        'dept' => $this->dept->id,
    ]));

    $response->assertOk();
    $response->assertSee('Back to Department');
    $response->assertSee(route('admin.departments.show', ['dept' => $this->dept->id]));
});

test('user deactivate confirm view renders Back to User Details and hidden source input when source is details', function () {
    Gate::define('deactivateConfirm', fn () => true);

    $response = $this->actingAs($this->adminUser)->get(route('admin.users.deactivateConfirm', [
        'user' => $this->cwdUser,
        'source' => 'details',
    ]));

    $response->assertOk();
    $response->assertSee('Back to User Details');
    $response->assertSee('<input type="hidden" name="source" value="details">', false);
    $response->assertSee(route('admin.users.show', ['user' => $this->cwdUser]));
});

test('team delete confirm view renders Back to Team Details and hidden source input when source is details', function () {
    Gate::define('deleteConfirm', fn () => true);

    $response = $this->actingAs($this->adminUser)->get(route('admin.teams.deleteConfirm', [
        'team' => $this->team,
        'source' => 'details',
    ]));

    $response->assertOk();
    $response->assertSee('Back to Team Details');
    $response->assertSee('<input type="hidden" name="source" value="details">', false);
    $response->assertSee(route('admin.teams.show', ['team' => $this->team]));
});

test('team details view renders member profile link with source=team and team id', function () {
    Gate::define('view', fn () => true);
    $this->team->members()->attach($this->cwdUser->id, ['team_role_id' => $this->memberRole->id]);

    $response = $this->actingAs($this->adminUser)->get(route('admin.teams.show', [
        'team' => $this->team,
        'dept' => $this->dept->id,
    ]));

    $response->assertOk();
    $response->assertSee(route('admin.users.show', [
        'user' => $this->cwdUser,
        'source' => 'team',
        'team' => $this->team->id,
        'dept' => $this->dept->id,
    ]));
});

test('user details view dynamically renders Back to Team link when source is team', function () {
    Gate::define('view', fn () => true);
    Gate::define('update', fn () => true);

    $response = $this->actingAs($this->adminUser)->get(route('admin.users.show', [
        'user' => $this->cwdUser,
        'source' => 'team',
        'team' => $this->team->id,
        'dept' => $this->dept->id,
    ]));

    $response->assertOk();
    $response->assertSee('Back to Team');
    $response->assertSee(route('admin.teams.show', [
        'team' => $this->team->id,
        'source' => 'department',
        'dept' => $this->dept->id,
    ]));
});

test('user details view renders assigned team link with source=user and user id', function () {
    Gate::define('view', fn () => true);
    Gate::define('update', fn () => true);
    $this->team->members()->attach($this->adminUser->id, ['team_role_id' => $this->memberRole->id]);

    $response = $this->actingAs($this->adminUser)->get(route('admin.users.show', [
        'user' => $this->adminUser,
    ]));

    $response->assertOk();
    $response->assertSee(route('admin.teams.show', [
        'team' => $this->team,
        'source' => 'user',
        'user' => $this->adminUser->id,
    ]));
});

test('team details view renders Back to User Profile link when source is user', function () {
    Gate::define('view', fn () => true);

    $response = $this->actingAs($this->adminUser)->get(route('admin.teams.show', [
        'team' => $this->team,
        'source' => 'user',
        'user' => $this->adminUser->id,
    ]));

    $response->assertOk();
    $response->assertSee('Back to User Profile');
    $response->assertSee(route('admin.users.show', ['user' => $this->adminUser->id]));
});

test('department details view renders Back to User Profile and Back to Team based on source', function () {
    Gate::define('view', fn () => true);

    // When source=user
    $responseUser = $this->actingAs($this->adminUser)->get(route('admin.departments.show', [
        'dept' => $this->dept->id,
        'source' => 'user',
        'user' => $this->adminUser->id,
    ]));
    $responseUser->assertOk();
    $responseUser->assertSee('Back to User Profile');
    $responseUser->assertSee(route('admin.users.show', ['user' => $this->adminUser->id]));

    // When source=team
    $responseTeam = $this->actingAs($this->adminUser)->get(route('admin.departments.show', [
        'dept' => $this->dept->id,
        'source' => 'team',
        'team' => $this->team->id,
    ]));
    $responseTeam->assertOk();
    $responseTeam->assertSee('Back to Team');
    $responseTeam->assertSee(route('admin.teams.show', ['team' => $this->team->id]));
});

test('user edit form cancel link returns to Team details when source is team', function () {
    Gate::define('userForm', fn () => true);

    $response = $this->actingAs($this->adminUser)->get(route('admin.users.editForm', [
        'user' => $this->cwdUser,
        'source' => 'team',
        'team' => $this->team->id,
    ]));

    $response->assertOk();
    $response->assertSee(route('admin.teams.show', ['team' => $this->team->id]));
    $response->assertSee('<input type="hidden" name="source" value="team">', false);
    $response->assertSee('<input type="hidden" name="team" value="'.$this->team->id.'">', false);
});

test('consumer details view renders ticket view details link with source=consumer', function () {
    Gate::define('view', fn () => true);

    $consumer = Consumer::create([
        'id' => '01JCON00000000000000000001',
        'acct_no' => '1234567890',
        'acct_code' => 'ACC-001',
        'name' => 'John Consumer',
        'address' => 'Barangay San Jose, Puerto Princesa',
        'status' => 'A',
    ]);

    $ticket = Ticket::create([
        'id' => '01JTIC00000000000000000001',
        'ticket_number' => 'TCK-20261011-0001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_id' => $consumer->id,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'San Jose',
        'complaint_description' => 'Power fluctuation reported.',
        'created_by_id' => $this->cwdUser->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $response = $this->actingAs($this->cwdUser)->get(route('cwd.consumers.show', $consumer));

    $response->assertOk();
    $response->assertSee(route('cwd.tickets.show', [
        'ticket' => $ticket->id,
        'source' => 'consumer',
        'consumer' => $consumer->id,
    ]));
});

test('ticket details view dynamically renders Back to Consumer Profile when source is consumer', function () {
    Gate::define('webView', fn () => true);

    $consumer = Consumer::create([
        'id' => '01JCON00000000000000000002',
        'acct_no' => '1234567891',
        'acct_code' => 'ACC-002',
        'name' => 'Alice Consumer',
        'address' => 'Barangay San Pedro, Puerto Princesa',
        'status' => 'A',
    ]);

    $ticket = Ticket::create([
        'id' => '01JTIC00000000000000000002',
        'ticket_number' => 'TCK-20261011-0002',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_id' => $consumer->id,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'San Pedro',
        'complaint_description' => 'Low voltage issue.',
        'created_by_id' => $this->cwdUser->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $response = $this->actingAs($this->cwdUser)->get(route('cwd.tickets.show', [
        'ticket' => $ticket->id,
        'source' => 'consumer',
        'consumer' => $consumer->id,
    ]));

    $response->assertOk();
    $response->assertSee('Back to Consumer Profile');
    $response->assertSee(route('cwd.consumers.show', ['consumer' => $consumer->id]));
});

test('ticket details view renders consumer profile link with source=ticket', function () {
    Gate::define('webView', fn () => true);

    $consumer = Consumer::create([
        'id' => '01JCON00000000000000000003',
        'acct_no' => '1234567892',
        'acct_code' => 'ACC-003',
        'name' => 'Bob Consumer',
        'address' => 'Barangay Sta. Monica, Puerto Princesa',
        'status' => 'A',
    ]);

    $ticket = Ticket::create([
        'id' => '01JTIC00000000000000000003',
        'ticket_number' => 'TCK-20261011-0003',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_id' => $consumer->id,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Sta. Monica',
        'complaint_description' => 'Sparks on pole wire.',
        'created_by_id' => $this->cwdUser->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $response = $this->actingAs($this->cwdUser)->get(route('cwd.tickets.show', [
        'ticket' => $ticket->id,
    ]));

    $response->assertOk();
    $response->assertSee(route('cwd.consumers.show', [
        'consumer' => $consumer->id,
        'source' => 'ticket',
        'ticket' => $ticket->id,
    ]));
});

test('consumer details view renders Back to Ticket when source is ticket', function () {
    Gate::define('view', fn () => true);

    $consumer = Consumer::create([
        'id' => '01JCON00000000000000000004',
        'acct_no' => '1234567893',
        'acct_code' => 'ACC-004',
        'name' => 'Charlie Consumer',
        'address' => 'Barangay Tiniguiban, Puerto Princesa',
        'status' => 'A',
    ]);

    $ticket = Ticket::create([
        'id' => '01JTIC00000000000000000004',
        'ticket_number' => 'TCK-20261011-0004',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_id' => $consumer->id,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'complaint_description' => 'Meter inspection request.',
        'created_by_id' => $this->cwdUser->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $response = $this->actingAs($this->cwdUser)->get(route('cwd.consumers.show', [
        'consumer' => $consumer->id,
        'source' => 'ticket',
        'ticket' => $ticket->id,
    ]));

    $response->assertOk();
    $response->assertSee('Back to Ticket');
    $response->assertSee(route('cwd.tickets.show', ['ticket' => $ticket->id]));
});

test('endorsement details view renders Back to Ticket when source is ticket', function () {
    Gate::define('view', fn () => true);

    $ticket = Ticket::create([
        'id' => '01JTIC00000000000000000005',
        'ticket_number' => 'TCK-20261011-0005',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'San Jose',
        'complaint_description' => 'Cross-department issue.',
        'created_by_id' => $this->cwdUser->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $endorsement = TicketEndorsement::create([
        'id' => '01JEND00000000000000000002',
        'ticket_id' => $ticket->id,
        'created_by_id' => $this->cwdUser->id,
        'suggested_department_id' => $this->dept->id,
        'reason' => 'Routing to technical department.',
        'status' => 'pending',
        'pre_endorsement_status' => 'open',
    ]);

    $response = $this->actingAs($this->cwdUser)->get(route('cwd.endorsements.show', [
        'endorsement' => $endorsement->id,
        'source' => 'ticket',
        'ticket' => $ticket->id,
    ]));

    $response->assertOk();
    $response->assertSee('Back to Ticket');
    $response->assertSee(route('cwd.tickets.show', ['ticket' => $ticket->id]));
});

test('ticket details view renders endorsement history link with source=ticket', function () {
    Gate::define('webView', fn () => true);

    $ticket = Ticket::create([
        'id' => '01JTIC00000000000000000006',
        'ticket_number' => 'TCK-20261011-0006',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'San Jose',
        'complaint_description' => 'Endorsement history check.',
        'created_by_id' => $this->cwdUser->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $endorsement = TicketEndorsement::create([
        'id' => '01JEND00000000000000000003',
        'ticket_id' => $ticket->id,
        'created_by_id' => $this->cwdUser->id,
        'suggested_department_id' => $this->dept->id,
        'reason' => 'Requires substation crew.',
        'status' => 'pending',
        'pre_endorsement_status' => 'open',
    ]);

    $response = $this->actingAs($this->cwdUser)->get(route('cwd.tickets.show', [
        'ticket' => $ticket->id,
    ]));

    $response->assertOk();
    $response->assertSee(route('cwd.endorsements.show', [
        'endorsement' => $endorsement->id,
        'source' => 'ticket',
        'ticket' => $ticket->id,
    ]));
});

test('ticket hierarchy tree preserves parent and child source params', function () {
    Gate::define('webView', fn () => true);

    $parentTicket = Ticket::create([
        'id' => '01JTIC00000000000000000007',
        'ticket_number' => 'TCK-20261011-0007',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'San Jose',
        'complaint_description' => 'Main outage cluster.',
        'created_by_id' => $this->cwdUser->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $childTicket = Ticket::create([
        'id' => '01JTIC00000000000000000008',
        'ticket_number' => 'TCK-20261011-0008',
        'parent_ticket_id' => $parentTicket->id,
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'San Jose',
        'complaint_description' => 'Sub-task child ticket.',
        'created_by_id' => $this->cwdUser->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    // Parent details renders child link with source=parent&parent={parentId}
    $responseParent = $this->actingAs($this->cwdUser)->get(route('cwd.tickets.show', [
        'ticket' => $parentTicket->id,
    ]));
    $responseParent->assertOk();
    $responseParent->assertSee(route('cwd.tickets.show', [
        'ticket' => $childTicket->id,
        'source' => 'parent',
        'parent' => $parentTicket->id,
    ]));

    // Child details with source=parent renders Back to Parent Ticket
    $responseChild = $this->actingAs($this->cwdUser)->get(route('cwd.tickets.show', [
        'ticket' => $childTicket->id,
        'source' => 'parent',
        'parent' => $parentTicket->id,
    ]));
    $responseChild->assertOk();
    $responseChild->assertSee('Back to Parent Ticket');
    $responseChild->assertSee(route('cwd.tickets.show', ['ticket' => $parentTicket->id]));
});

