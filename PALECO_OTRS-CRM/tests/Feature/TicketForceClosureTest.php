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
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['database.default' => 'force_close_test', 'database.connections.force_close_test' => [
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
        $table->boolean('is_force_closed')->default(false);
        $table->text('closure_reason')->nullable();
        $table->string('closed_by_id', 26)->nullable();
        $table->text('cancellation_reason')->nullable();
        $table->string('created_by_id', 26);
        $table->timestamp('reported_at')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('resolved_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->timestamp('cancelled_at')->nullable();
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
    $this->otherDept = Department::create(['dept_name' => 'Customer Accounts']);

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

    $this->foreignSupervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_foreign',
        'first_name' => 'Foreign',
        'last_name' => 'Supervisor',
        'contact' => '09123456782',
        'role_id' => $supervisorRole->id,
        'department_id' => $this->otherDept->id,
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

test('cwd officer can force-close an assigned ticket with freely typed justification via web', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-1001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Feeder tripped.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'team_id' => $this->team->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::ASSIGNED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    TicketAssignment::create([
        'ticket_id' => $ticket->id,
        'team_id' => $this->team->id,
        'assigned_by_id' => $this->supervisorUser->id,
        'reason' => 'Dispatch to site',
        'unassigned_at' => null,
    ]);

    $response = $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.forceClose', $ticket), [
            'closure_reason' => 'Consumer refused linemen access to private transformer post.',
        ]);

    $response->assertRedirect(route('cwd.tickets.show', $ticket));
    $response->assertSessionHas('success');

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::CLOSED);
    expect($ticket->is_force_closed)->toBeTrue();
    expect($ticket->closure_reason)->toBe('Consumer refused linemen access to private transformer post.');
    expect($ticket->closed_by_id)->toBe($this->cwdUser->id);
    expect($ticket->closed_at)->not->toBeNull();
    expect($ticket->team_id)->toBeNull();

    // Verify active assignment was terminated
    expect(TicketAssignment::where('ticket_id', $ticket->id)->whereNull('unassigned_at')->count())->toBe(0);

    // Verify transition logged
    expect(TicketStatusLog::where('ticket_id', $ticket->id)
        ->where('old_status', TicketStatus::ASSIGNED->value)
        ->where('new_status', TicketStatus::CLOSED->value)
        ->exists())->toBeTrue();

    // Zero orphaned remarks
    expect(TicketRemark::where('ticket_id', $ticket->id)->count())->toBe(0);

    // Verify Spatie Activity Log recorded administrative force-closure description
    $activity = \Spatie\Activitylog\Models\Activity::where('subject_id', $ticket->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();
    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe("Ticket {$ticket->ticket_number} was administratively force-closed.");
    expect($activity->causer_id)->toBe($this->cwdUser->id);
});

test('authorized cwd user can view force close confirmation page while admin is forbidden', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-FC-CONFIRM',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Test force close prompt.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::ASSIGNED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // CWD Officer
    $cwdResponse = $this->actingAs($this->cwdUser)
        ->get(route('cwd.tickets.forceCloseConfirm', $ticket));
    $cwdResponse->assertOk();
    $cwdResponse->assertSee('Force Close Service Ticket?');
    $cwdResponse->assertSee('closure_reason');

    // Admin cannot access CWD force close confirm
    $adminResponse = $this->actingAs($this->adminUser)
        ->get(route('cwd.tickets.forceCloseConfirm', $ticket));
    $adminResponse->assertForbidden();
});

test('admin cannot force close ticket and is forbidden', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-ADMIN-FC',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Test admin force close.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::ASSIGNED->value,
        'created_by_id' => $this->adminUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('cwd.tickets.forceClose', $ticket), [
            'closure_reason' => 'Administrative intervention: line clear.',
        ]);

    $response->assertForbidden();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::ASSIGNED);
    expect($ticket->is_force_closed)->toBeFalse();
});

test('force closure is permitted from in-progress, resolved, and endorsed statuses', function () {
    $service = app(TicketService::class);

    foreach ([TicketStatus::IN_PROGRESS, TicketStatus::RESOLVED, TicketStatus::ENDORSED] as $status) {
        $ticket = Ticket::create([
            'id' => (string) Str::ulid(),
            'ticket_number' => 'TCK-STATUS-' . $status->value,
            'complaint_source' => ComplaintSources::PHONE_CALL->value,
            'consumer_contact' => '09123456789',
            'complaint_description' => 'Test complaint.',
            'department_id' => $this->dept->id,
            'category_id' => $this->category->id,
            'barangay' => 'Tiniguiban',
            'status' => $status->value,
            'created_by_id' => $this->cwdUser->id,
            'reported_at' => now(),
        ]);

        $closed = $service->forceCloseTicket($ticket, [
            'closure_reason' => "Force closure from {$status->value}",
        ], $this->cwdUser);

        expect($closed->status)->toBe(TicketStatus::CLOSED);
        expect($closed->is_force_closed)->toBeTrue();
    }
});

test('open tickets cannot be force-closed; must use cancellation workflow', function () {
    $openTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-OPEN-001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Fresh intake.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.forceClose', $openTicket), [
            'closure_reason' => 'Attempted force closure on open ticket',
        ]);

    $response->assertForbidden();

    $openTicket->refresh();
    expect($openTicket->status)->toBe(TicketStatus::OPEN);
});

test('tickets pending endorsement review cannot be force-closed', function () {
    $pendingTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-PEND-001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Endorsement transfer awaiting review.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::PENDING_ENDORSEMENT->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.forceClose', $pendingTicket), [
            'closure_reason' => 'Attempted force closure while pending endorsement',
        ]);

    $response->assertForbidden();

    $pendingTicket->refresh();
    expect($pendingTicket->status)->toBe(TicketStatus::PENDING_ENDORSEMENT);
});

test('already closed or cancelled tickets cannot be force-closed', function () {
    $closedTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-CLS-001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Closed ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::CLOSED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $cancelledTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-CAN-001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Cancelled ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::CANCELLED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.forceClose', $closedTicket), [
            'closure_reason' => 'Double close attempt',
        ])
        ->assertForbidden();

    $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.forceClose', $cancelledTicket), [
            'closure_reason' => 'Force close cancelled ticket',
        ])
        ->assertForbidden();
});

test('validation fails when closure justification is missing or shorter than 3 characters', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-VAL-001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Assigned ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::ASSIGNED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $this->actingAs($this->cwdUser)
        ->post(route('cwd.tickets.forceClose', $ticket), [
            'closure_reason' => 'no',
        ])
        ->assertSessionHasErrors('closure_reason');
});

test('only cwd officer is authorized to force-close tickets; supervisor and field personnel are forbidden', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-ROLE-FC-001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Force close target for role checks.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'team_id' => $this->team->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::IN_PROGRESS->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // 1. Policy checks: Only CWD is allowed
    expect(Gate::forUser($this->cwdUser)->allows('forceClose', $ticket))->toBeTrue();
    expect(Gate::forUser($this->supervisorUser)->allows('forceClose', $ticket))->toBeFalse();
    expect(Gate::forUser($this->adminUser)->allows('forceClose', $ticket))->toBeFalse();
    expect(Gate::forUser($this->fieldUser)->allows('forceClose', $ticket))->toBeFalse();

    // 2. Service level enforcement: Non-CWD throws ValidationException
    $service = app(TicketService::class);

    expect(fn () => $service->forceCloseTicket($ticket, [
        'closure_reason' => 'Supervisor unauthorized attempt',
    ], $this->supervisorUser))->toThrow(ValidationException::class);

    expect(fn () => $service->forceCloseTicket($ticket, [
        'closure_reason' => 'Admin unauthorized attempt',
    ], $this->adminUser))->toThrow(ValidationException::class);

    expect(fn () => $service->forceCloseTicket($ticket, [
        'closure_reason' => 'Field lineman unauthorized attempt',
    ], $this->fieldUser))->toThrow(ValidationException::class);

    // 3. Web route enforcement: Non-CWD gets 403 Forbidden
    $this->actingAs($this->supervisorUser)
        ->post(route('cwd.tickets.forceClose', $ticket), [
            'closure_reason' => 'Supervisor attempt',
        ])->assertForbidden();

    $this->actingAs($this->fieldUser)
        ->post(route('cwd.tickets.forceClose', $ticket), [
            'closure_reason' => 'Lineman attempt',
        ])->assertForbidden();
});

test('cannot force-close parent ticket while any subordinate child tickets are NOT exactly closed or cancelled', function () {
    $parentTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-PARENT-001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Root outage.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::ASSIGNED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // Subordinate Child 1: CLOSED (valid terminal state)
    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-PARENT-001-1',
        'parent_ticket_id' => $parentTicket->id,
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Subordinate task 1.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::CLOSED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // Subordinate Child 2: IN_PROGRESS (non-terminal state!)
    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-PARENT-001-2',
        'parent_ticket_id' => $parentTicket->id,
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Subordinate task 2 still working.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::IN_PROGRESS->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $service = app(TicketService::class);

    expect(fn () => $service->forceCloseTicket($parentTicket, [
        'closure_reason' => 'Force close with active child',
    ], $this->cwdUser))->toThrow(ValidationException::class);
});

test('can force-close parent ticket when ALL subordinate child tickets are exactly closed or cancelled', function () {
    $parentTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-PARENT-002',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Root outage with finalized children.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::ASSIGNED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // Subordinate Child 1: CLOSED
    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-PARENT-002-1',
        'parent_ticket_id' => $parentTicket->id,
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Child 1 resolved and closed.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::CLOSED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // Subordinate Child 2: CANCELLED
    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-PARENT-002-2',
        'parent_ticket_id' => $parentTicket->id,
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Child 2 cancelled.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::CANCELLED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $service = app(TicketService::class);

    $closed = $service->forceCloseTicket($parentTicket, [
        'closure_reason' => 'All children closed or cancelled, parent administratively force-closed.',
    ], $this->cwdUser);

    expect($closed->status)->toBe(TicketStatus::CLOSED);
    expect($closed->is_force_closed)->toBeTrue();
});

test('child ticket can be independently force-closed without affecting the parent ticket', function () {
    $parentTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-PARENT-003',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Ongoing parent work.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::IN_PROGRESS->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $childTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-PARENT-003-1',
        'parent_ticket_id' => $parentTicket->id,
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Subordinate secondary line fix.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::ASSIGNED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $service = app(TicketService::class);

    $closedChild = $service->forceCloseTicket($childTicket, [
        'closure_reason' => 'Secondary line fixed by private consumer electrician.',
    ], $this->cwdUser);

    expect($closedChild->status)->toBe(TicketStatus::CLOSED);
    expect($closedChild->is_force_closed)->toBeTrue();

    // Verify parent status remains intact (IN_PROGRESS)
    $parentTicket->refresh();
    expect($parentTicket->status)->toBe(TicketStatus::IN_PROGRESS);
});

test('cannot spawn child tickets from a closed ticket via web policy or service', function () {
    $closedTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-CLOSED-SPAWN',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Closed ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::CLOSED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // 1. Web policy denies
    expect(Gate::forUser($this->cwdUser)->allows('createChild', $closedTicket))->toBeFalse();

    // 2. Service layer rejects
    $service = app(TicketService::class);
    expect(fn () => $service->createManualChildTicket($closedTicket, [
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'complaint_description' => 'Spawn child from closed parent',
        'barangay' => 'Tiniguiban',
    ]))->toThrow(ValidationException::class);
});
