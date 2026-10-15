<?php

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Models\AccountRole;
use App\Models\Consumer;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['database.default' => 'phase4_test', 'database.connections.phase4_test' => [
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

    Schema::create('ticket_categories', function (Blueprint $table) {
        $table->id();
        $table->string('category_name');
        $table->string('category_desc')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('consumers', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('acct_no')->unique();
        $table->string('acct_code')->unique();
        $table->string('name');
        $table->string('address');
        $table->string('status', 1)->default('A');
        $table->string('meter_serial')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('tickets', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_number')->unique();
        $table->string('parent_ticket_id', 26)->nullable();
        $table->string('consumer_id', 26)->nullable();
        $table->foreignId('department_id')->constrained();
        $table->foreignId('category_id')->nullable();
        $table->string('team_id', 26)->nullable();
        $table->string('complaint_source');
        $table->string('consumer_contact');
        $table->text('complaint_description');
        $table->boolean('other_category')->default(false);
        $table->string('other_category_name')->nullable();
        $table->string('purok')->nullable();
        $table->string('street')->nullable();
        $table->string('barangay');
        $table->string('landmark')->nullable();
        $table->string('status')->default(TicketStatus::OPEN->value);
        $table->string('created_by_id', 26);
        $table->timestamp('reported_at');
        $table->softDeletes();
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

    Schema::create('teams', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('team_name');
        $table->string('team_desc')->nullable();
        $table->time('shift_start');
        $table->time('shift_end');
        $table->foreignId('department_id')->nullable();
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
        $table->string('changed_by_id', 26);
        $table->timestamps();
    });

    // Seed roles
    $cwdRole = AccountRole::create(['role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);
    $adminRole = AccountRole::create(['role_name' => 'Administrator', 'slug_identifier' => 'admin']);
    $supervisorRole = AccountRole::create(['role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);

    $this->dept = Department::create(['dept_name' => 'Technical Services']);
    $this->otherDept = Department::create(['dept_name' => 'Metering Services']);

    $this->category = TicketCategory::create(['category_name' => 'Power Outage']);
    $this->otherCategory = TicketCategory::create(['category_name' => 'Voltage Fluctuation']);

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

    $this->consumer = Consumer::create([
        'id' => (string) Str::ulid(),
        'acct_no' => '1001002003',
        'acct_code' => '02-0504-8538',
        'name' => 'Juan Dela Cruz',
        'address' => 'Brgy. Tiniguiban, Puerto Princesa City',
    ]);

    Gate::define('access-cwd_officer', fn ($user) => $user->role->slug_identifier === 'cwd_officer');
    Gate::define('access-admin', fn ($user) => $user->role->slug_identifier === 'admin');
});

test('cwd officer can view the edit form for an open ticket', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Fluctuating power in the area.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->cwdUser)->get(route('cwd.tickets.editForm', $ticket));

    $response->assertOk();
    $response->assertSee('Edit service ticket');
    $response->assertSee($ticket->ticket_number);
    $response->assertSee('09123456789');
    $response->assertSee('Tiniguiban');
});

test('cwd officer can update clerical intake details on an open ticket', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0002',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Original description with typo.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'purok' => 'Purok 1',
        'landmark' => 'Near Store',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->cwdUser)->put(route('cwd.tickets.update', $ticket), [
        'complaint_source' => ComplaintSources::WALK_IN->value,
        'consumer_contact' => '09987654321',
        'complaint_description' => 'Corrected complaint description with accurate details.',
        'department_id' => $this->otherDept->id,
        'category_id' => $this->otherCategory->id,
        'other_category' => 0,
        'barangay' => 'San Pedro',
        'purok' => 'Purok Bayanihan',
        'street' => 'Rizal Avenue',
        'landmark' => 'Across National High School',
        'link_consumer' => 0,
    ]);

    $response->assertRedirect(route('cwd.tickets.show', $ticket));
    $response->assertSessionHas('success');

    $ticket->refresh();
    expect($ticket->complaint_source)->toBe(ComplaintSources::WALK_IN)
        ->and($ticket->consumer_contact)->toBe('09987654321')
        ->and($ticket->complaint_description)->toBe('Corrected complaint description with accurate details.')
        ->and($ticket->department_id)->toBe($this->otherDept->id)
        ->and($ticket->category_id)->toBe($this->otherCategory->id)
        ->and($ticket->barangay)->toBe('San Pedro')
        ->and($ticket->purok)->toBe('Purok Bayanihan')
        ->and($ticket->street)->toBe('Rizal Avenue')
        ->and($ticket->landmark)->toBe('Across National High School');

    // Confirm automatic internal remark was logged
    expect($ticket->remarks()->where('body', 'Clerical intake details updated.')->exists())->toBeTrue();
});

test('submitting ticket update without changes redirects with info alert and creates no remarks', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0099',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Unchanged description.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'other_category' => 0,
        'barangay' => 'Tiniguiban',
        'purok' => 'Purok 1',
        'street' => 'National Highway',
        'landmark' => 'Near Store',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->cwdUser)->put(route('cwd.tickets.update', $ticket), [
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Unchanged description.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'other_category' => 0,
        'barangay' => 'Tiniguiban',
        'purok' => 'Purok 1',
        'street' => 'National Highway',
        'landmark' => 'Near Store',
        'link_consumer' => 0,
    ]);

    $response->assertRedirect(route('cwd.tickets.show', $ticket));
    $response->assertSessionHas('info', "No changes were detected for ticket {$ticket->ticket_number}.");
    $response->assertSessionMissing('success');

    // Confirm NO remarks were created
    expect($ticket->remarks()->count())->toBe(0);
});

test('cwd officer can link or unlink consumer during clerical update', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0003',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'No power.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'consumer_id' => null,
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // 1. Link consumer
    $this->actingAs($this->cwdUser)->put(route('cwd.tickets.update', $ticket), [
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'No power in main switch.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'other_category' => 0,
        'purok' => 'Purok 1',
        'street' => 'National Highway',
        'barangay' => 'Tiniguiban',
        'link_consumer' => 1,
        'account_code' => $this->consumer->acct_code,
    ]);

    $ticket->refresh();
    expect($ticket->consumer_id)->toBe($this->consumer->id);

    // 2. Unlink consumer
    $this->actingAs($this->cwdUser)->put(route('cwd.tickets.update', $ticket), [
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'No power in main switch.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'other_category' => 0,
        'purok' => 'Purok 1',
        'street' => 'National Highway',
        'barangay' => 'Tiniguiban',
        'link_consumer' => 0,
    ]);

    $ticket->refresh();
    expect($ticket->consumer_id)->toBeNull();
});

test('updating ticket intake details is strictly forbidden when ticket is assigned or in progress', function () {
    $assignedTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0004',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Dispatched already.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::ASSIGNED->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // Edit form access denied
    $formResponse = $this->actingAs($this->cwdUser)->get(route('cwd.tickets.editForm', $assignedTicket));
    $formResponse->assertForbidden();

    // Put update denied
    $updateResponse = $this->actingAs($this->cwdUser)->put(route('cwd.tickets.update', $assignedTicket), [
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09000000000',
        'complaint_description' => 'Trying to change assigned ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'other_category' => 0,
        'purok' => 'Purok 1',
        'street' => 'National Highway',
        'barangay' => 'Tiniguiban',
        'link_consumer' => 0,
    ]);
    $updateResponse->assertForbidden();

    // Confirm data was not modified
    $assignedTicket->refresh();
    expect($assignedTicket->consumer_contact)->toBe('09123456789')
        ->and($assignedTicket->complaint_description)->toBe('Dispatched already.');
});

test('non-cwd roles cannot access the ticket edit form or perform updates', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0005',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Open ticket.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // Supervisor attempt
    $this->actingAs($this->supervisorUser)
        ->get(route('cwd.tickets.editForm', $ticket))
        ->assertForbidden();

    $this->actingAs($this->supervisorUser)
        ->put(route('cwd.tickets.update', $ticket), [
            'complaint_source' => ComplaintSources::PHONE_CALL->value,
            'consumer_contact' => '09000000000',
            'complaint_description' => 'Hacked description.',
            'department_id' => $this->dept->id,
            'category_id' => $this->category->id,
            'other_category' => 0,
            'purok' => 'Purok 1',
            'street' => 'National Highway',
            'barangay' => 'Tiniguiban',
            'link_consumer' => 0,
        ])
        ->assertForbidden();
});

test('updateIntakeDetails under pessimistic lock rejects update if ticket was concurrently assigned', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0006',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Power out in purok.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // In-memory model object still thinks it's OPEN, but another process changes DB to ASSIGNED
    \Illuminate\Support\Facades\DB::table('tickets')
        ->where('id', $ticket->id)
        ->update(['status' => TicketStatus::ASSIGNED->value]);

    $ticketService = app(\App\Services\Tickets\TicketService::class);

    expect(fn () => $ticketService->updateIntakeDetails($ticket, [
        'complaint_source' => ComplaintSources::PHONE_CALL,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Updated complaint description.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'other_category' => 0,
        'barangay' => 'Tiniguiban',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('ticket update controller catches concurrency validation exception and redirects with error', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0007',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Power out in purok.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    // Mock TicketService to throw ValidationException during update
    $mockService = \Mockery::mock(\App\Services\Tickets\TicketService::class);
    $mockService->shouldReceive('updateIntakeDetails')
        ->andThrow(\Illuminate\Validation\ValidationException::withMessages([
            'status' => 'This ticket is no longer in Open status.',
        ]));
    $this->app->instance(\App\Services\Tickets\TicketService::class, $mockService);

    $response = $this->actingAs($this->cwdUser)->put(route('cwd.tickets.update', $ticket), [
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Updated complaint description.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'other_category' => 0,
        'purok' => 'Purok 1',
        'street' => 'National Highway',
        'barangay' => 'Tiniguiban',
        'link_consumer' => 0,
    ]);

    $response->assertRedirect(route('cwd.tickets.show', $ticket));
    $response->assertSessionHas('error', 'This ticket is no longer in Open status.');
});

test('assignTicket under pessimistic lock catches cross-department mismatch if ticket department changed concurrently', function () {
    $dept2 = Department::create(['dept_name' => 'CSD', 'dept_desc' => 'Consumer Services']);

    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-0008',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Original description.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->cwdUser->id,
        'reported_at' => now(),
    ]);

    $team = \App\Models\Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Alpha Team',
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'department_id' => $this->dept->id,
    ]);

    // Simulate concurrent ticket re-routing to Dept 2 in DB
    \Illuminate\Support\Facades\DB::table('tickets')
        ->where('id', $ticket->id)
        ->update(['department_id' => $dept2->id]);

    $ticketService = app(\App\Services\Tickets\TicketService::class);

    expect(fn () => $ticketService->assignTicket($ticket, $team->id, $this->supervisorUser))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});


