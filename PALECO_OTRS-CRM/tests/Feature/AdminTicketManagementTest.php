<?php

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\ActivityLogs\ActivityLogService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    config(['database.default' => 'admin_ticket_test', 'database.connections.admin_ticket_test' => [
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
        $table->boolean('is_active')->default(true);
        $table->string('password');
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('ticket_categories', function (Blueprint $table) {
        $table->id();
        $table->string('category_name');
        $table->text('category_desc')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('teams', function (Blueprint $table) {
        $table->id();
        $table->string('team_name');
        $table->foreignId('department_id')->constrained();
        $table->time('shift_start')->nullable();
        $table->time('shift_end')->nullable();
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
        $table->foreignId('ticket_id');
        $table->string('old_status')->nullable();
        $table->string('new_status');
        $table->string('changed_by_id', 26)->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_assignments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ticket_id');
        $table->foreignId('team_id');
        $table->string('assigned_by', 26);
        $table->text('reason')->nullable();
        $table->timestamp('unassigned_at')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_endorsements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ticket_id');
        $table->foreignId('suggested_dept_id');
        $table->string('created_by_id', 26)->nullable();
        $table->string('reviewed_by', 26)->nullable();
        $table->string('status')->default('pending');
        $table->text('endorsement_reason')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_accomplishments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ticket_id');
        $table->string('accomplished_by', 26);
        $table->string('approved_by', 26)->nullable();
        $table->string('rejected_by', 26)->nullable();
        $table->string('status')->default('pending');
        $table->timestamp('accomplished_at')->useCurrent();
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_remarks', function (Blueprint $table) {
        $table->ulid('id')->primary();
        $table->foreignId('ticket_id');
        $table->string('user_id', 26);
        $table->text('body');
        $table->boolean('is_internal')->default(false);
        $table->timestamps();
    });

    $adminRole = AccountRole::create(['role_name' => 'Administrator', 'slug_identifier' => 'admin']);
    $cwdRole = AccountRole::create(['role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);

    $this->adminUser = User::create([
        'id' => '01JUSERADM00000000000000001',
        'username' => 'adminuser',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'contact' => '09111111111',
        'role_id' => $adminRole->id,
        'is_active' => true,
        'password' => bcrypt('password'),
    ]);

    $this->cwdUser = User::create([
        'id' => '01JUSERCWD00000000000000002',
        'username' => 'cwdofficer',
        'first_name' => 'CWD',
        'last_name' => 'Officer',
        'contact' => '09222222222',
        'role_id' => $cwdRole->id,
        'is_active' => true,
        'password' => bcrypt('password'),
    ]);

    $this->dept = Department::create(['dept_name' => 'Technical Operations']);
    $this->category = TicketCategory::create(['category_name' => 'Line Fault']);

    $this->ticket = Ticket::create([
        'id' => '01JTICKET000000000000000001',
        'ticket_number' => 'TKT-2026-00001',
        'complaint_source' => ComplaintSources::PHONE_CALL,
        'category_id' => $this->category->id,
        'department_id' => $this->dept->id,
        'status' => TicketStatus::OPEN,
        'complaint_description' => 'Power transformer sparking near pole 4.',
        'barangay' => 'San Pedro',
        'purok' => 'Purok 1',
        'street' => 'Rizal Avenue',
        'created_by_id' => $this->cwdUser->id,
    ]);
});

test('admin can access shared ticket management index page in read-only mode', function () {
    $response = $this->actingAs($this->adminUser)->get(route('admin.tickets'));

    $response->assertStatus(200);
    $response->assertSee('Tickets');
    $response->assertSee('Read-Only');
    $response->assertDontSee('New Ticket');
});

test('admin can access shared ticket details page with read-only badge and no mutate actions', function () {
    $response = $this->actingAs($this->adminUser)->get(route('admin.tickets.show', ['ticket' => $this->ticket->id]));

    $response->assertStatus(200);
    $response->assertSee($this->ticket->ticket_number);
    $response->assertSee('Read-Only');
    $response->assertDontSee('Create Child Ticket');
    $response->assertDontSee('Add Operational Remark');
});

test('admin is prohibited from creating tickets or accessing cwd mutating endpoints', function () {
    // Admin cannot view ticket creation form (CWD route returns 404 via anti-enumeration gate)
    $response = $this->actingAs($this->adminUser)->get(route('cwd.tickets.createForm'));
    $response->assertStatus(404);

    // Admin cannot POST to store tickets
    $response = $this->actingAs($this->adminUser)->post(route('cwd.tickets.store'), [
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'category_id' => $this->category->id,
        'complaint_description' => 'Test admin creation attempt',
        'barangay' => 'San Pedro',
    ]);
    $response->assertStatus(404);

    // Admin cannot view child ticket form
    $response = $this->actingAs($this->adminUser)->get(route('cwd.tickets.children.createForm', ['ticket' => $this->ticket->id]));
    $response->assertStatus(404);

    // Admin cannot POST child tickets
    $response = $this->actingAs($this->adminUser)->post(route('cwd.tickets.children.store', ['ticket' => $this->ticket->id]), [
        'category_id' => $this->category->id,
        'complaint_description' => 'Child ticket test attempt',
    ]);
    $response->assertStatus(404);

    // Admin cannot POST remarks to shared remarks endpoint (policy denies)
    $response = $this->actingAs($this->adminUser)->post(route('shared.tickets.remarks.store', ['ticket' => $this->ticket->id]), [
        'body' => 'Admin remark attempt',
    ]);
    $response->assertStatus(403);
});

test('cwd officer can access ticket management with full creation controls', function () {
    $response = $this->actingAs($this->cwdUser)->get(route('cwd.tickets'));

    $response->assertStatus(200);
    $response->assertSee('New Ticket');
    $response->assertDontSee('Read-Only');
});

test('cwd officer can access ticket details with full mutation controls', function () {
    $response = $this->actingAs($this->cwdUser)->get(route('cwd.tickets.show', ['ticket' => $this->ticket->id]));

    $response->assertStatus(200);
    $response->assertSee('Create Child Ticket');
    $response->assertSee('Add Operational Remark');
    $response->assertDontSee('Read-Only');
});

test('cwd officer cannot access admin tickets endpoint', function () {
    $response = $this->actingAs($this->cwdUser)->get(route('admin.tickets'));
    $response->assertStatus(404);

    $response = $this->actingAs($this->cwdUser)->get(route('admin.tickets.show', ['ticket' => $this->ticket->id]));
    $response->assertStatus(404);
});

test('activity log service resolves subject url to admin ticket route for admin user', function () {
    $service = app(ActivityLogService::class);

    $activity = Activity::create([
        'log_name' => 'tickets',
        'description' => 'created ticket',
        'subject_type' => Ticket::class,
        'subject_id' => $this->ticket->id,
        'causer_type' => User::class,
        'causer_id' => $this->cwdUser->id,
        'properties' => ['ip_address' => '127.0.0.1'],
    ]);

    // As Admin
    auth()->login($this->adminUser);
    $resultAdmin = $service->getActivityDetail($activity);
    expect($resultAdmin['subject']['url'])->toBe(route('admin.tickets.show', ['ticket' => $this->ticket->id]));

    // As CWD Officer
    auth()->login($this->cwdUser);
    $resultCwd = $service->getActivityDetail($activity);
    expect($resultCwd['subject']['url'])->toBe(route('cwd.tickets.show', ['ticket' => $this->ticket->id]));
});
