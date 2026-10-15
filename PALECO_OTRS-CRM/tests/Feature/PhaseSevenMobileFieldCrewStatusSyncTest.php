<?php

use App\Enums\TicketStatus;
use App\Events\TicketAccomplished;
use App\Events\TicketAssigned;
use App\Events\TicketCancelled;
use App\Events\TicketForceClosed;
use App\Events\TicketVerifiedAndClosed;
use App\Events\TicketWorkStarted;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\Tickets\TicketService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['database.default' => 'phase7_test', 'database.connections.phase7_test' => [
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

    Schema::create('team_roles', function (Blueprint $table) {
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
        $table->timestamp('last_login')->nullable();
        $table->timestamp('locked_until')->nullable();
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
        $table->foreignId('department_id')->nullable();
        $table->string('team_id', 26)->nullable();
        $table->foreignId('category_id')->nullable();
        $table->string('status')->default('open');
        $table->string('created_by_id', 26)->nullable();
        $table->string('closed_by_id', 26)->nullable();
        $table->string('parent_ticket_id', 26)->nullable();
        $table->text('cancellation_reason')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->boolean('is_force_closed')->default(false);
        $table->text('closure_reason')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('resolved_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->boolean('is_offline_synced')->default(false);
        $table->timestamp('synced_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
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
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    $this->dept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $this->fieldRole = AccountRole::create(['id' => 1, 'role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);
    $this->supRole = AccountRole::create(['id' => 2, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);
    $this->cwdRole = AccountRole::create(['id' => 3, 'role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);
    $this->linemanRole = TeamRole::create(['id' => 1, 'role_name' => 'Lineman', 'slug_identifier' => 'lineman']);

    $this->supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_alex',
        'first_name' => 'Alex',
        'last_name' => 'Cruz',
        'contact' => '09123456780',
        'password' => Hash::make('password'),
        'role_id' => $this->supRole->id,
        'department_id' => $this->dept->id,
    ]);

    $this->cwdOfficer = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'cwd_officer',
        'first_name' => 'CWD',
        'last_name' => 'Officer',
        'contact' => '09123456781',
        'password' => Hash::make('password'),
        'role_id' => $this->cwdRole->id,
        'department_id' => null,
    ]);

    $this->team = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Bravo Emergency Line Crew',
        'department_id' => $this->dept->id,
    ]);

    $this->category = TicketCategory::create([
        'id' => 1,
        'category_name' => 'Emergency Transformer Fault',
        'category_desc' => 'EMG',
    ]);
});

test('TicketAssigned event broadcasts to team channel in addition to operations and department channels', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261015-001',
        'department_id' => $this->dept->id,
        'team_id' => $this->team->id,
        'status' => TicketStatus::ASSIGNED->value,
    ]);

    $event = new TicketAssigned($ticket);
    $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();

    expect($channels)->toContain('private-cwd.operations')
        ->and($channels)->toContain('private-team.'.$this->team->id)
        ->and($channels)->toContain('private-department.'.$this->dept->id)
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $ticket->id,
            'ticket_number' => 'TCK-20261015-001',
            'status' => 'assigned',
            'team_id' => $this->team->id,
            'team_name' => 'Bravo Emergency Line Crew',
        ]);
});

test('TicketCancelled event broadcasts to assigned team channel to halt field crew operations', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261015-002',
        'department_id' => $this->dept->id,
        'team_id' => $this->team->id,
        'status' => TicketStatus::CANCELLED->value,
        'cancellation_reason' => 'Consumer called back: Power restored spontaneously.',
        'cancelled_at' => now(),
    ]);

    $event = new TicketCancelled($ticket);
    $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();

    expect($channels)->toContain('private-cwd.operations')
        ->and($channels)->toContain('private-team.'.$this->team->id)
        ->and($channels)->toContain('private-department.'.$this->dept->id)
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $ticket->id,
            'ticket_number' => 'TCK-20261015-002',
            'status' => 'cancelled',
            'cancellation_reason' => 'Consumer called back: Power restored spontaneously.',
        ]);
});

test('TicketForceClosed event broadcasts to assigned team channel to terminate work order immediately', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261015-003',
        'department_id' => $this->dept->id,
        'team_id' => $this->team->id,
        'status' => TicketStatus::CLOSED->value,
        'is_force_closed' => true,
        'closure_reason' => 'Substation grid reset completed by engineering team.',
        'closed_by_id' => $this->cwdOfficer->id,
        'closed_at' => now(),
    ]);

    $event = new TicketForceClosed($ticket);
    $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();

    expect($channels)->toContain('private-cwd.operations')
        ->and($channels)->toContain('private-team.'.$this->team->id)
        ->and($channels)->toContain('private-department.'.$this->dept->id)
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $ticket->id,
            'ticket_number' => 'TCK-20261015-003',
            'status' => 'closed',
            'is_force_closed' => true,
            'closure_reason' => 'Substation grid reset completed by engineering team.',
        ]);
});

test('TicketWorkStarted and TicketVerifiedAndClosed broadcast to the assigned team channel', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261015-004',
        'department_id' => $this->dept->id,
        'team_id' => $this->team->id,
        'status' => TicketStatus::IN_PROGRESS->value,
        'started_at' => now(),
    ]);

    $workStartedEvent = new TicketWorkStarted($ticket);
    $channelsWork = collect($workStartedEvent->broadcastOn())->map(fn ($c) => $c->name)->all();
    expect($channelsWork)->toContain('private-team.'.$this->team->id);

    $ticket->update(['status' => TicketStatus::CLOSED->value, 'closed_at' => now()]);
    $closedEvent = new TicketVerifiedAndClosed($ticket);
    $channelsClosed = collect($closedEvent->broadcastOn())->map(fn ($c) => $c->name)->all();
    expect($channelsClosed)->toContain('private-team.'.$this->team->id);
});

test('TicketService assignTicket dispatches TicketAssigned targeting the team channel', function () {
    Event::fake([TicketAssigned::class]);

    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261015-005',
        'department_id' => $this->dept->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $service = app(TicketService::class);
    $service->assignTicket($ticket, $this->team->id, $this->supervisor, 'Proceed immediately with protective equipment.');

    Event::assertDispatched(TicketAssigned::class, function ($event) {
        $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();
        return in_array('private-team.'.$this->team->id, $channels, true)
            && in_array('private-cwd.operations', $channels, true)
            && $event->ticket->status === TicketStatus::ASSIGNED;
    });
});

test('TicketService cancelTicket dispatches TicketCancelled targeting operations and department channels', function () {
    Event::fake([TicketCancelled::class]);

    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261015-006',
        'department_id' => $this->dept->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $service = app(TicketService::class);
    $service->cancelTicket($ticket, ['cancellation_reason' => 'Customer cancelled request.'], $this->cwdOfficer);

    Event::assertDispatched(TicketCancelled::class, function ($event) {
        $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();
        return in_array('private-cwd.operations', $channels, true)
            && in_array('private-department.'.$this->dept->id, $channels, true)
            && $event->ticket->status === TicketStatus::CANCELLED;
    });
});

test('TicketService forceCloseTicket dispatches TicketForceClosed targeting the team channel', function () {
    Event::fake([TicketForceClosed::class]);

    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261015-007',
        'department_id' => $this->dept->id,
        'team_id' => $this->team->id,
        'status' => TicketStatus::IN_PROGRESS->value,
    ]);

    $service = app(TicketService::class);
    $service->forceCloseTicket($ticket, ['closure_reason' => 'Emergency declared resolved by command center.'], $this->cwdOfficer);

    Event::assertDispatched(TicketForceClosed::class, function ($event) {
        $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();
        return in_array('private-team.'.$this->team->id, $channels, true)
            && in_array('private-cwd.operations', $channels, true)
            && $event->ticket->status === TicketStatus::CLOSED;
    });
});
