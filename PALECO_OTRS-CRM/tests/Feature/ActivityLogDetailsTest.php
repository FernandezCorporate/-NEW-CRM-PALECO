<?php

use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    config(['database.default' => 'activity_details_test', 'database.connections.activity_details_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);

    Schema::create('activity_log', function (Blueprint $table) {
        $table->id();
        $table->string('log_name')->nullable()->index();
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

    Schema::create('tickets', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_number')->unique();
        $table->string('status')->default('open');
        $table->foreignId('department_id')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    $this->roleAdmin = AccountRole::create(['role_name' => 'Administrator', 'slug_identifier' => 'admin']);
    $this->roleCwd = AccountRole::create(['role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);

    $this->dept = Department::create(['dept_name' => 'Administration', 'dept_desc' => 'Admin dept']);

    $this->adminUser = User::create([
        'id' => '01JADMIN000000000000000001',
        'username' => 'sysadmin',
        'first_name' => 'System',
        'last_name' => 'Admin',
        'contact' => '09123456789',
        'role_id' => $this->roleAdmin->id,
        'department_id' => $this->dept->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $this->cwdUser = User::create([
        'id' => '01JCWD00000000000000000001',
        'username' => 'cwdofficer',
        'first_name' => 'Officer',
        'last_name' => 'Cwd',
        'contact' => '09123456788',
        'role_id' => $this->roleCwd->id,
        'department_id' => $this->dept->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
});

test('unauthenticated user is redirected to login when accessing activity details', function () {
    $activity = Activity::create([
        'log_name' => 'Tickets',
        'description' => 'Ticket #101 created',
        'event' => 'created',
    ]);

    $this->get(route('admin.monitoring.show', $activity))
        ->assertRedirect(route('login'));
});

test('non-admin user is denied access to activity details', function () {
    $activity = Activity::create([
        'log_name' => 'Tickets',
        'description' => 'Ticket #101 created',
        'event' => 'created',
    ]);

    $this->actingAs($this->cwdUser)
        ->get(route('admin.monitoring.show', $activity))
        ->assertNotFound();
});

test('admin can view activity details for model update event with attribute diffs', function () {
    $ticket = Ticket::create([
        'id' => '01JTICKET00000000000000001',
        'ticket_number' => 'TKT-261012-001',
        'status' => 'in_progress',
        'department_id' => $this->dept->id,
    ]);

    $activity = Activity::create([
        'log_name' => 'Tickets',
        'description' => 'TKT-261012-001 status changed from assigned to in_progress.',
        'subject_type' => Ticket::class,
        'subject_id' => $ticket->id,
        'event' => 'updated',
        'causer_type' => User::class,
        'causer_id' => $this->adminUser->id,
        'attribute_changes' => [
            'old' => ['status' => 'assigned'],
            'attributes' => ['status' => 'in_progress'],
        ],
        'properties' => [
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/154.0.0.0',
        ],
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('admin.monitoring.show', $activity));

    $response->assertOk()
        ->assertSee('LOG #' . $activity->id)
        ->assertSee('TKT-261012-001 status changed')
        ->assertSee('System Admin')
        ->assertSee('@sysadmin')
        ->assertSee('192.168.1.100')
        ->assertSee('Chrome on Windows')
        ->assertSee('TKT-261012-001')
        ->assertSee('Status')
        ->assertSee('assigned')
        ->assertSee('in_progress')
        ->assertSee('Raw Audit Forensics');
});

test('admin can view activity details for model created event', function () {
    $ticket = Ticket::create([
        'id' => '01JTICKET00000000000000002',
        'ticket_number' => 'TKT-261012-002',
        'status' => 'open',
        'department_id' => $this->dept->id,
    ]);

    $activity = Activity::create([
        'log_name' => 'Tickets',
        'description' => 'TKT-261012-002 has been created.',
        'subject_type' => Ticket::class,
        'subject_id' => $ticket->id,
        'event' => 'created',
        'causer_type' => User::class,
        'causer_id' => $this->adminUser->id,
        'attribute_changes' => [
            'attributes' => [
                'ticket_number' => 'TKT-261012-002',
                'status' => 'open',
            ],
        ],
        'properties' => [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PostmanRuntime/7.39.0',
        ],
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('admin.monitoring.show', $activity));

    $response->assertOk()
        ->assertSee('TKT-261012-002 has been created.')
        ->assertSee('Postman API Client')
        ->assertSee('TKT-261012-002')
        ->assertSee('open');
});

test('admin can view activity details for non-model authentication event', function () {
    $activity = Activity::create([
        'log_name' => 'login_success',
        'description' => 'Login attempt was successful.',
        'subject_type' => null,
        'subject_id' => null,
        'event' => 'login',
        'causer_type' => User::class,
        'causer_id' => $this->adminUser->id,
        'attribute_changes' => [],
        'properties' => [
            'role' => 'admin',
            'email' => 'admin@paleco.com',
            'contact' => '09123456789',
            'username' => 'sysadmin',
            'full_name' => 'System Admin',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15',
        ],
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('admin.monitoring.show', $activity));

    $response->assertOk()
        ->assertSee('Login attempt was successful.')
        ->assertSee('Successful Login')
        ->assertSee('No Target Subject')
        ->assertSee('admin@paleco.com')
        ->assertSee('Safari on macOS')
        ->assertSee('10.0.0.1');

});

test('handles soft-deleted subject gracefully with archived badge', function () {
    $ticket = Ticket::create([
        'id' => '01JTICKET00000000000000003',
        'ticket_number' => 'TKT-261012-003',
        'status' => 'closed',
        'department_id' => $this->dept->id,
    ]);
    $ticket->delete();

    $activity = Activity::create([
        'log_name' => 'Tickets',
        'description' => 'TKT-261012-003 has been archived.',
        'subject_type' => Ticket::class,
        'subject_id' => $ticket->id,
        'event' => 'deleted',
        'causer_type' => User::class,
        'causer_id' => $this->adminUser->id,
        'attribute_changes' => [
            'old' => ['status' => 'closed'],
        ],
        'properties' => [
            'ip_address' => '127.0.0.1',
        ],
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('admin.monitoring.show', $activity));

    $response->assertOk()
        ->assertSee('Archived')
        ->assertSee('TKT-261012-003');
});

test('handles permanently deleted subject gracefully without error', function () {
    $activity = Activity::create([
        'log_name' => 'Tickets',
        'description' => 'Ticket was deleted permanently.',
        'subject_type' => Ticket::class,
        'subject_id' => '01NONEXISTENT0000000000001',
        'event' => 'deleted',
        'causer_type' => null,
        'causer_id' => null,
        'attribute_changes' => null,
        'properties' => [
            'ip_address' => '127.0.0.1',
        ],
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('admin.monitoring.show', $activity));

    $response->assertOk()
        ->assertSee('Deleted')
        ->assertSee('Ticket #01NONEXISTENT0000000000001')
        ->assertSee('No Attribute Mutations Recorded');
});


test('monitoring index table renders Details link pointing to activity details', function () {
    $activity = Activity::create([
        'log_name' => 'Tickets',
        'description' => 'Testing index details link',
        'event' => 'created',
        'causer_type' => User::class,
        'causer_id' => $this->adminUser->id,
        'properties' => ['ip_address' => '127.0.0.1'],
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('admin.monitoring.index'));

    $response->assertOk()
        ->assertSee('Action')
        ->assertSee(route('admin.monitoring.show', $activity))
        ->assertSee('Details');
});
