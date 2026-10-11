<?php

use App\Enums\NonModelActions;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    config(['database.default' => 'monitoring_test', 'database.connections.monitoring_test' => [
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

    Schema::create('teams', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('team_name');
        $table->foreignId('department_id');
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('tickets', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_number')->unique();
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
});

test('unauthenticated user cannot access system monitoring', function () {
    $response = $this->get(route('admin.monitoring.index'));

    $response->assertRedirect(route('login'));
});

test('non-admin user cannot access system monitoring and receives not found', function () {
    $response = $this->actingAs($this->cwdUser)->get(route('admin.monitoring.index'));

    $response->assertNotFound();
});

test('admin user can access system monitoring and see server-provided dropdown options', function () {
    $response = $this->actingAs($this->adminUser)->get(route('admin.monitoring.index'));

    $response->assertOk();
    $response->assertSee('System Monitoring & Audit Log');
    // Category dropdown options
    $response->assertSee('All Categories');
    $response->assertSee('Authentication');
    $response->assertSee('Tickets');
    $response->assertSee('Users');
    $response->assertSee('Teams');
    $response->assertSee('Departments');
    $response->assertSee('Ticket Categories');
    // Event type dropdown options: all, create, update, delete, other
    $response->assertSee('All Events');
    $response->assertSee('Create');
    $response->assertSee('Update');
    $response->assertSee('Delete');
    $response->assertSee('Other');
    $response->assertDontSee('(non crud)');
});

test('filtering by category auth returns all non-model actions including successful, failed, and deactivated logins', function () {
    // 1. Success login
    Activity::create([
        'log_name' => NonModelActions::LOGIN_SUCCESS->value,
        'description' => NonModelActions::LOGIN_SUCCESS->description(),
        'event' => NonModelActions::LOGIN_SUCCESS->event(),
        'causer_type' => User::class,
        'causer_id' => $this->adminUser->id,
        'properties' => ['ip_address' => '127.0.0.1', 'username' => 'sysadmin'],
    ]);

    // 2. Failed login (no causer)
    Activity::create([
        'log_name' => NonModelActions::LOGIN_FAILED->value,
        'description' => NonModelActions::LOGIN_FAILED->description(),
        'event' => NonModelActions::LOGIN_FAILED->event(),
        'properties' => ['ip_address' => '192.168.1.100', 'username' => 'intruder'],
    ]);

    // 3. Deactivated account login
    Activity::create([
        'log_name' => NonModelActions::LOGIN_ACCOUNT_DEACTIVATED->value,
        'description' => NonModelActions::LOGIN_ACCOUNT_DEACTIVATED->description(),
        'event' => NonModelActions::LOGIN_ACCOUNT_DEACTIVATED->event(),
        'causer_type' => User::class,
        'causer_id' => $this->cwdUser->id,
        'properties' => ['ip_address' => '10.0.0.1', 'username' => 'cwdofficer'],
    ]);

    // 4. Model CRUD event
    Activity::create([
        'log_name' => 'Tickets',
        'description' => 'TCK-20261011-0001 has been created.',
        'event' => 'created',
        'subject_type' => 'App\Models\Ticket',
        'subject_id' => '01JTIC00000000000000000001',
        'causer_type' => User::class,
        'causer_id' => $this->adminUser->id,
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('admin.monitoring.index', ['category' => 'auth']));

    $response->assertOk();
    $response->assertSee(NonModelActions::LOGIN_SUCCESS->description());
    $response->assertSee(NonModelActions::LOGIN_FAILED->description());
    $response->assertSee(NonModelActions::LOGIN_ACCOUNT_DEACTIVATED->description());
    $response->assertDontSee('TCK-20261011-0001 has been created.');
});

test('filtering by legacy category login_success still queries all cases of non model event enums', function () {
    Activity::create([
        'log_name' => NonModelActions::LOGIN_SUCCESS->value,
        'description' => 'User logged in successfully.',
        'event' => 'login',
        'properties' => ['ip_address' => '127.0.0.1'],
    ]);

    Activity::create([
        'log_name' => NonModelActions::LOGIN_FAILED->value,
        'description' => 'Failed password attempt detected.',
        'event' => 'login',
        'properties' => ['ip_address' => '192.168.1.200'],
    ]);

    Activity::create([
        'log_name' => 'Users',
        'description' => 'User Jane Doe has been modified.',
        'event' => 'updated',
        'subject_type' => User::class,
        'subject_id' => $this->cwdUser->id,
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('admin.monitoring.index', ['category' => 'login_success']));

    $response->assertOk();
    $response->assertSee('User logged in successfully.');
    $response->assertSee('Failed password attempt detected.');
    $response->assertDontSee('User Jane Doe has been modified.');
});

test('filtering by event_type create returns created operations and excludes updates, deletes, and auth events', function () {
    Activity::create([
        'log_name' => 'Tickets',
        'description' => 'TCK-20261011-0002 has been created.',
        'event' => 'created',
        'subject_type' => 'App\Models\Ticket',
        'subject_id' => '01JTIC00000000000000000002',
    ]);

    Activity::create([
        'log_name' => 'Teams',
        'description' => 'Line Crew Alpha has been modified.',
        'event' => 'updated',
        'subject_type' => 'App\Models\Team',
        'subject_id' => '01JTEAM0000000000000000001',
    ]);

    Activity::create([
        'log_name' => 'Department',
        'description' => 'Substation unit has been deleted.',
        'event' => 'deleted',
        'subject_type' => Department::class,
        'subject_id' => (string) $this->dept->id,
    ]);

    Activity::create([
        'log_name' => NonModelActions::LOGIN_FAILED->value,
        'description' => 'Unauthorized login attempt rejected.',
        'event' => 'login',
        'properties' => ['ip_address' => '192.168.1.105'],
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('admin.monitoring.index', ['event_type' => 'create']));

    $response->assertOk();
    $response->assertSee('TCK-20261011-0002 has been created.');
    $response->assertDontSee('Line Crew Alpha has been modified.');
    $response->assertDontSee('Substation unit has been deleted.');
    $response->assertDontSee('Unauthorized login attempt rejected.');
});

test('filtering by event_type update returns updated operations', function () {
    Activity::create([
        'log_name' => 'Tickets',
        'description' => 'TCK-20261011-0003 has been created.',
        'event' => 'created',
    ]);

    Activity::create([
        'log_name' => 'Teams',
        'description' => 'Line Crew Beta has been modified.',
        'event' => 'updated',
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('admin.monitoring.index', ['event_type' => 'update']));

    $response->assertOk();
    $response->assertSee('Line Crew Beta has been modified.');
    $response->assertDontSee('TCK-20261011-0003 has been created.');
});

test('filtering by event_type delete returns deleted operations', function () {
    Activity::create([
        'log_name' => 'Teams',
        'description' => 'Line Crew Gamma has been modified.',
        'event' => 'updated',
    ]);

    Activity::create([
        'log_name' => 'Department',
        'description' => 'Old Department has been deleted.',
        'event' => 'deleted',
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('admin.monitoring.index', ['event_type' => 'delete']));

    $response->assertOk();
    $response->assertSee('Old Department has been deleted.');
    $response->assertDontSee('Line Crew Gamma has been modified.');
});

test('filtering by event_type other returns non-CRUD authentication milestones', function () {
    Activity::create([
        'log_name' => NonModelActions::LOGIN_SUCCESS->value,
        'description' => 'Admin successfully signed in.',
        'event' => 'login',
        'properties' => ['ip_address' => '127.0.0.1'],
    ]);

    Activity::create([
        'log_name' => NonModelActions::LOGIN_FAILED->value,
        'description' => 'Invalid password for username operator.',
        'event' => 'login',
        'properties' => ['ip_address' => '172.16.0.5'],
    ]);

    Activity::create([
        'log_name' => 'Department',
        'description' => 'Technical Services has been modified.',
        'event' => 'updated',
        'subject_type' => Department::class,
        'subject_id' => (string) $this->dept->id,
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('admin.monitoring.index', ['event_type' => 'other']));

    $response->assertOk();
    $response->assertSee('Admin successfully signed in.');
    $response->assertSee('Invalid password for username operator.');
    $response->assertDontSee('Technical Services has been modified.');
});

test('combining category and event_type filters yields expected intersection', function () {
    Activity::create([
        'log_name' => 'Tickets',
        'description' => 'TCK-20261011-0099 has been created.',
        'event' => 'created',
        'subject_type' => 'App\Models\Ticket',
        'subject_id' => '01JTIC00000000000000000099',
    ]);

    Activity::create([
        'log_name' => 'Tickets',
        'description' => 'TCK-20261011-0099 has been modified.',
        'event' => 'updated',
        'subject_type' => 'App\Models\Ticket',
        'subject_id' => '01JTIC00000000000000000099',
    ]);

    Activity::create([
        'log_name' => NonModelActions::LOGIN_FAILED->value,
        'description' => 'Failed auth check.',
        'event' => 'login',
        'properties' => ['ip_address' => '192.168.1.1'],
    ]);

    // Tickets + create: matches Tickets created only
    $resTicketsCreate = $this->actingAs($this->adminUser)->get(route('admin.monitoring.index', [
        'category' => 'Tickets',
        'event_type' => 'create',
    ]));
    $resTicketsCreate->assertOk();
    $resTicketsCreate->assertSee('TCK-20261011-0099 has been created.');
    $resTicketsCreate->assertDontSee('TCK-20261011-0099 has been modified.');
    $resTicketsCreate->assertDontSee('Failed auth check.');

    // Auth + create: mutually exclusive -> yields empty
    $resAuthCreate = $this->actingAs($this->adminUser)->get(route('admin.monitoring.index', [
        'category' => 'auth',
        'event_type' => 'create',
    ]));
    $resAuthCreate->assertOk();
    $resAuthCreate->assertSee('No activity logs found');
    $resAuthCreate->assertDontSee('Failed auth check.');
    $resAuthCreate->assertDontSee('TCK-20261011-0099 has been created.');
});

test('search query searches description, log_name, event, and properties JSON', function () {
    Activity::create([
        'log_name' => NonModelActions::LOGIN_FAILED->value,
        'description' => 'Suspicious brute-force attempt.',
        'event' => 'login',
        'properties' => ['ip_address' => '203.0.113.195', 'username' => 'hacker_bot'],
    ]);

    Activity::create([
        'log_name' => 'Tickets',
        'description' => 'Standard routine work order.',
        'event' => 'created',
        'properties' => ['ip_address' => '10.0.0.5'],
    ]);

    // Search by property ip_address
    $resIp = $this->actingAs($this->adminUser)->get(route('admin.monitoring.index', ['search' => '203.0.113.195']));
    $resIp->assertOk();
    $resIp->assertSee('Suspicious brute-force attempt.');
    $resIp->assertDontSee('Standard routine work order.');

    // Search by username in properties
    $resUser = $this->actingAs($this->adminUser)->get(route('admin.monitoring.index', ['search' => 'hacker_bot']));
    $resUser->assertOk();
    $resUser->assertSee('Suspicious brute-force attempt.');
    $resUser->assertDontSee('Standard routine work order.');
});

test('sidebar profile card renders fullName in proper case rather than raw lowercase', function () {
    $lowercaseUser = User::create([
        'id' => '01JLOWUSER0000000000000001',
        'username' => 'lowercaseadmin',
        'first_name' => 'maria',
        'middle_name' => 'santos',
        'last_name' => 'reyes',
        'name_ext' => 'jr',
        'contact' => '09123456780',
        'role_id' => $this->roleAdmin->id,
        'department_id' => $this->dept->id,
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $response = $this->actingAs($lowercaseUser)->get(route('admin.monitoring.index'));

    $response->assertOk();
    // Verifies fullName accessor title-casing and format
    $response->assertSee('Maria S. Reyes, JR');
    $response->assertDontSee('maria reyes');
    // Verifies avatarInitials accessor
    $response->assertSee('MR');
});
