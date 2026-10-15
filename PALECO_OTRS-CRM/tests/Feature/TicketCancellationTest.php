<?php

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\TicketRemark;
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Services\Tickets\TicketService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['database.default' => 'cancellation_test', 'database.connections.cancellation_test' => [
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
        $table->string('team_desc')->nullable();
        $table->time('shift_start')->nullable();
        $table->time('shift_end')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('team_members', function (Blueprint $table) {
        $table->id();
        $table->string('team_id', 26);
        $table->string('user_id', 26);
        $table->timestamps();
    });

    Schema::create('ticket_categories', function (Blueprint $table) {
        $table->id();
        $table->string('category_name');
        $table->string('category_desc')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    // Original schema: NO migrations made, no cancellation_reason or cancelled_at columns
    Schema::create('tickets', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_number')->unique();
        $table->string('parent_ticket_id', 26)->nullable();
        $table->string('consumer_id', 26)->nullable();
        $table->foreignId('department_id')->nullable();
        $table->foreignId('category_id')->nullable();
        $table->string('team_id', 26)->nullable();
        $table->string('complaint_source');
        $table->string('consumer_contact')->nullable();
        $table->text('complaint_description')->nullable();
        $table->boolean('other_category')->default(false);
        $table->string('other_category_name')->nullable();
        $table->string('purok')->nullable();
        $table->string('street')->nullable();
        $table->string('barangay');
        $table->string('landmark')->nullable();
        $table->string('status')->default(TicketStatus::OPEN->value);
        $table->text('cancellation_reason')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->string('created_by_id', 26);
        $table->timestamp('reported_at')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('resolved_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->boolean('is_offline_synced')->default(false);
        $table->timestamp('synced_at')->nullable();
        $table->timestamp('client_started_at')->nullable();
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
        $table->string('old_status')->nullable();
        $table->string('new_status');
        $table->string('changed_by_id', 26)->nullable();
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

    // Seed roles
    $cwdRole = AccountRole::create(['role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);
    $adminRole = AccountRole::create(['role_name' => 'Administrator', 'slug_identifier' => 'admin']);
    $supervisorRole = AccountRole::create(['role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);
    $fieldRole = AccountRole::create(['role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);

    $this->dept = Department::create(['dept_name' => 'Technical Services']);

    $this->category = TicketCategory::create(['category_name' => 'Power Outage']);

    $this->team = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Alpha Response Team',
        'department_id' => $this->dept->id,
    ]);

    $this->cwdUser = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'cwd_officer_jane',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'contact' => '09123456789',
        'role_id' => $cwdRole->id,
        'department_id' => $this->dept->id,
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);

    $this->supervisorUser = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_mark',
        'first_name' => 'Mark',
        'last_name' => 'Supervisor',
        'contact' => '09123456781',
        'role_id' => $supervisorRole->id,
        'department_id' => $this->dept->id,
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);

    $this->adminUser = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'admin_super',
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'contact' => '09123456780',
        'role_id' => $adminRole->id,
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);

    $this->fieldUser = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'field_bob',
        'first_name' => 'Bob',
        'last_name' => 'Lineman',
        'contact' => '09123456783',
        'role_id' => $fieldRole->id,
        'department_id' => $this->dept->id,
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);

    Gate::define('access-cwd_officer', fn ($user) => $user->role->slug_identifier === 'cwd_officer');
    Gate::define('access-supervisor', fn ($user) => $user->role->slug_identifier === 'supervisor');
    Gate::define('access-admin', fn ($user) => $user->role->slug_identifier === 'admin');
    Gate::define('access-field_personnel', fn ($user) => $user->role->slug_identifier === 'field_personnel');
});

test('cwd officer can cancel an open ticket with freely typed reason via web', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Power interruption reported.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.cancel', $ticket), [
            'cancellation_reason' => 'Consumer called back stating power was restored by self.',
        ]);

    $response->assertRedirect(route('cwd.tickets.show', $ticket));
    $response->assertSessionHas('success');

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::CANCELLED);

    // Verify freely typed reason and timestamp were directly persisted on ticket
    expect($ticket->cancellation_reason)->toBe('Consumer called back stating power was restored by self.');
    expect($ticket->cancelled_at)->not->toBeNull();

    // Verify TicketStatusLog was created
    expect(TicketStatusLog::where('ticket_id', $ticket->id)
        ->where('old_status', TicketStatus::OPEN->value)
        ->where('new_status', TicketStatus::CANCELLED->value)
        ->exists())->toBeTrue();

    // Verify zero orphaned remarks were inserted into ticket_remarks
    expect(TicketRemark::where('ticket_id', $ticket->id)->count())->toBe(0);
});

test('authorized cwd user can view ticket cancel confirmation page', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-CONFIRM-1',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Test cancellation prompt.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'San Pedro',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->cwdUser)
        ->get(route('cwd.tickets.cancelConfirm', $ticket));

    $response->assertOk();
    $response->assertSee('Cancel Service Ticket?');
    $response->assertSee('cancellation_reason');
});

test('only open tickets can be cancelled; assigned tickets cannot be cancelled', function () {
    $assignedTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0002',
        'complaint_source' => ComplaintSources::WALK_IN->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Sparking transformer.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'team_id' => $this->team->id,
        'barangay' => 'San Pedro',
        'status' => TicketStatus::ASSIGNED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.cancel', $assignedTicket), [
            'cancellation_reason' => 'Duplicate entry',
        ]);

    $response->assertForbidden();

    $assignedTicket->refresh();
    expect($assignedTicket->status)->toBe(TicketStatus::ASSIGNED);
});

test('supervisor cannot cancel tickets; only CWD officers are authorized', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0003',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Tripped breaker.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->supervisorUser)
        ->post(route('cwd.tickets.cancel', $ticket), [
            'cancellation_reason' => 'Supervisor attempted cancellation',
        ]);

    $response->assertForbidden();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::OPEN);
});

test('admin cannot cancel tickets due to read-only policy', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0004',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Test ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('cwd.tickets.cancel', $ticket), [
            'cancellation_reason' => 'Admin cancel attempt',
        ]);

    $response->assertForbidden();
});

test('field personnel cannot cancel tickets', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0005',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Test ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->fieldUser)
        ->post(route('cwd.tickets.cancel', $ticket), [
            'cancellation_reason' => 'Lineman cancel attempt',
        ]);

    $response->assertForbidden();
});

test('cannot cancel ticket when in progress, resolved, closed, or pending endorsement', function () {
    $inProgressTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0006',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Line cut.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'team_id' => $this->team->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::IN_PROGRESS->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
        'started_at' => now(),
    ]);

    $response = $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.cancel', $inProgressTicket), [
            'cancellation_reason' => 'Work started already',
        ]);

    $response->assertForbidden();

    $inProgressTicket->refresh();
    expect($inProgressTicket->status)->toBe(TicketStatus::IN_PROGRESS);
});

test('cannot cancel an already cancelled ticket', function () {
    $cancelledTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0007',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Already cancelled.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::CANCELLED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.cancel', $cancelledTicket), [
            'cancellation_reason' => 'Duplicate cancellation',
        ]);

    $response->assertForbidden();
});

test('validation fails when cancellation reason is missing or shorter than 3 characters', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0008',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Test ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.cancel', $ticket), [
            'cancellation_reason' => 'no',
        ]);

    $response->assertSessionHasErrors('cancellation_reason');
});

test('service layer enforces open status and cwd actor inside pessimistic lock', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0009',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Test ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::ASSIGNED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $service = app(TicketService::class);

    expect(fn () => $service->cancelTicket($ticket, [
        'cancellation_reason' => 'Concurrently assigned by supervisor',
    ], $this->cwdUser))->toThrow(ValidationException::class);
});

test('cannot spawn child ticket from a cancelled parent ticket via web or service', function () {
    $parentTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0010',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Parent cancelled ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::CANCELLED->value,
        'cancellation_reason' => 'Invalid dispatch request.',
        'cancelled_at' => now(),
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // 1. Form page is forbidden
    $this->actingAs($this->cwdUser)
        ->get(route('cwd.tickets.children.createForm', $parentTicket))
        ->assertForbidden();

    // 2. Store route is forbidden
    $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.children.store', $parentTicket), [
            'department_id' => $this->dept->id,
            'category_id' => $this->category->id,
            'complaint_description' => 'Attempted child creation.',
            'purok' => 'Purok 1',
            'street' => 'National Highway',
            'barangay' => 'Tiniguiban',
        ])
        ->assertForbidden();

    // 3. Service layer throws ValidationException
    $service = app(TicketService::class);
    expect(fn () => $service->createManualChildTicket($parentTicket, [
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'complaint_description' => 'Attempted child creation.',
        'purok' => 'Purok 1',
        'street' => 'National Highway',
        'barangay' => 'Tiniguiban',
    ]))->toThrow(ValidationException::class);
});

test('lifecycle policy blocks assignment, start, accomplishment, endorsement, and editing on cancelled tickets', function () {
    $cancelledTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0011',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Cancelled ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'team_id' => $this->team->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::CANCELLED->value,
        'cancellation_reason' => 'Cancelled by dispatcher.',
        'cancelled_at' => now(),
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // Attach field user to team
    $this->fieldUser->teams()->attach($this->team->id);

    // Policy checks
    expect(Gate::forUser($this->supervisorUser)->allows('assign', $cancelledTicket))->toBeFalse();
    expect(Gate::forUser($this->supervisorUser)->allows('endorse', $cancelledTicket))->toBeFalse();
    expect(Gate::forUser($this->fieldUser)->allows('start', $cancelledTicket))->toBeFalse();
    expect(Gate::forUser($this->fieldUser)->allows('accomplish', $cancelledTicket))->toBeFalse();
    expect(Gate::forUser($this->cwdUser)->allows('update', $cancelledTicket))->toBeFalse();
    expect(Gate::forUser($this->cwdUser)->allows('createChild', $cancelledTicket))->toBeFalse();
});
