<?php

use App\Enums\TicketAccomplishmentStatus;
use App\Enums\TicketStatus;
use App\Events\TicketAccomplished;
use App\Events\TicketAssigned;
use App\Events\TicketVerifiedAndClosed;
use App\Events\TicketWorkStarted;
use App\Livewire\Shared\TicketAssignedPersonnel;
use App\Livewire\Shared\TicketHierarchy;
use App\Livewire\Shared\TicketHistoryTabs;
use App\Livewire\Shared\TicketOperationalControls;
use App\Livewire\Shared\TicketStatusBadge;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\TicketEndorsement;
use App\Models\TicketRemark;
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Services\Tickets\TicketAccomplishmentService;
use App\Services\Tickets\TicketService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    config(['database.default' => 'phase3_test', 'database.connections.phase3_test' => [
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
        $table->string('contact')->nullable();
        $table->foreignId('role_id')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->timestamps();
    });

    Schema::create('teams', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('team_name');
        $table->foreignId('department_id');
        $table->time('shift_start')->nullable();
        $table->time('shift_end')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('team_members', function (Blueprint $table) {
        $table->string('team_id', 26);
        $table->string('user_id', 26);
        $table->timestamps();
        $table->primary(['team_id', 'user_id']);
    });

    Schema::create('ticket_categories', function (Blueprint $table) {
        $table->id();
        $table->string('category_name');
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('tickets', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_number')->unique();
        $table->string('parent_ticket_id', 26)->nullable();
        $table->string('status');
        $table->foreignId('department_id')->nullable();
        $table->string('team_id', 26)->nullable();
        $table->foreignId('category_id')->nullable();
        $table->string('complaint_source')->default('walk_in');
        $table->string('subject')->nullable();
        $table->text('complaint_description')->nullable();
        $table->timestamp('reported_at')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('client_started_at')->nullable();
        $table->timestamp('resolved_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->string('created_by_id', 26)->nullable();
        $table->string('closed_by_id', 26)->nullable();
        $table->boolean('is_force_closed')->default(false);
        $table->boolean('is_offline_synced')->default(false);
        $table->timestamp('synced_at')->nullable();
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

    Schema::create('ticket_endorsements', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_id', 26);
        $table->string('created_by_id', 26);
        $table->foreignId('suggested_department_id')->nullable();
        $table->text('reason');
        $table->string('status', 30)->default('pending');
        $table->string('pre_endorsement_status', 30);
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
        $table->string('consumer_name')->nullable();
        $table->string('signature_path')->nullable();
        $table->string('status');
        $table->string('approved_by_id', 26)->nullable();
        $table->string('rejected_by_id', 26)->nullable();
        $table->text('rejection_reason')->nullable();
        $table->timestamp('accomplished_at')->nullable();
        $table->timestamp('client_accomplished_at')->nullable();
        $table->boolean('is_offline_synced')->default(false);
        $table->timestamp('synced_at')->nullable();
        $table->string('idempotency_key')->nullable()->unique();
        $table->timestamps();
    });

    Schema::create('accomplishment_photos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('accomplishment_id');
        $table->string('file_path');
        $table->string('file_name');
        $table->integer('file_size');
        $table->string('mime_type');
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

    // Seed basic entities
    $supRole = AccountRole::create(['role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);
    $fieldRole = AccountRole::create(['role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);
    $cwdRole = AccountRole::create(['role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);
    $dept = Department::create(['dept_name' => 'Line Maintenance']);
    $category = TicketCategory::create(['category_name' => 'Power Outage']);

    $this->cwdOfficer = User::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'username' => 'cwd_mary',
        'first_name' => 'Mary',
        'last_name' => 'Cruz',
        'role_id' => $cwdRole->id,
    ]);

    $this->supervisor = User::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'username' => 'sup_john',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'role_id' => $supRole->id,
        'department_id' => $dept->id,
    ]);

    $this->worker = User::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'username' => 'field_alex',
        'first_name' => 'Alex',
        'last_name' => 'Reyes',
        'role_id' => $fieldRole->id,
        'department_id' => $dept->id,
    ]);

    $this->team = Team::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'team_name' => 'Crew Bravo',
        'department_id' => $dept->id,
    ]);
    $this->team->members()->attach($this->worker->id);

    $this->ticket = Ticket::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_number' => 'TKT-20261014-001',
        'status' => TicketStatus::OPEN->value,
        'department_id' => $dept->id,
        'category_id' => $category->id,
        'reported_at' => now(),
    ]);
});

test('TicketAssigned event broadcasts on cwd.operations with complete operational payload', function () {
    $this->ticket->update(['status' => TicketStatus::ASSIGNED, 'team_id' => $this->team->id]);
    $event = new TicketAssigned($this->ticket);

    $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();
    expect($channels)->toContain('private-cwd.operations')
        ->and($channels)->toContain('private-team.'.$this->team->id)
        ->and($event->broadcastAs())->toBe('TicketAssigned')
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $this->ticket->id,
            'ticket_number' => 'TKT-20261014-001',
            'status' => 'assigned',
            'status_label' => 'Assigned',
            'team_id' => $this->team->id,
            'team_name' => 'Crew Bravo',
        ]);
});

test('TicketWorkStarted event broadcasts on cwd.operations with complete operational payload', function () {
    $this->ticket->update(['status' => TicketStatus::IN_PROGRESS, 'team_id' => $this->team->id, 'started_at' => now()]);
    $event = new TicketWorkStarted($this->ticket);

    $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();
    expect($channels)->toContain('private-cwd.operations')
        ->and($channels)->toContain('private-team.'.$this->team->id)
        ->and($event->broadcastAs())->toBe('TicketWorkStarted')
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $this->ticket->id,
            'ticket_number' => 'TKT-20261014-001',
            'status' => 'in_progress',
            'status_label' => 'In Progress',
            'team_name' => 'Crew Bravo',
        ]);
});

test('TicketAccomplished event broadcasts on cwd.operations with complete operational payload', function () {
    $this->ticket->update(['status' => TicketStatus::RESOLVED, 'team_id' => $this->team->id, 'resolved_at' => now()]);
    $event = new TicketAccomplished($this->ticket);

    $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();
    expect($channels)->toContain('private-cwd.operations')
        ->and($channels)->toContain('private-team.'.$this->team->id)
        ->and($event->broadcastAs())->toBe('TicketAccomplished')
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $this->ticket->id,
            'ticket_number' => 'TKT-20261014-001',
            'status' => 'resolved',
            'status_label' => 'Resolved',
            'team_name' => 'Crew Bravo',
        ]);
});

test('TicketVerifiedAndClosed event broadcasts on cwd.operations with complete operational payload', function () {
    $this->ticket->update(['status' => TicketStatus::CLOSED, 'team_id' => $this->team->id, 'closed_at' => now()]);
    $event = new TicketVerifiedAndClosed($this->ticket);

    $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();
    expect($channels)->toContain('private-cwd.operations')
        ->and($channels)->toContain('private-team.'.$this->team->id)
        ->and($event->broadcastAs())->toBe('TicketVerifiedAndClosed')
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $this->ticket->id,
            'ticket_number' => 'TKT-20261014-001',
            'status' => 'closed',
            'status_label' => 'Closed',
        ]);
});

test('TicketService::assignTicket dispatches TicketAssigned event', function () {
    Event::fake([TicketAssigned::class]);

    $service = app(TicketService::class);
    $service->assignTicket($this->ticket, $this->team->id, $this->supervisor, 'Routine line maintenance dispatch');

    Event::assertDispatched(TicketAssigned::class, function ($event) {
        return $event->ticket->id === $this->ticket->id
            && $event->ticket->status === TicketStatus::ASSIGNED
            && $event->ticket->team_id === $this->team->id;
    });
});

test('TicketService::startTicket dispatches TicketWorkStarted event', function () {
    $this->ticket->update(['status' => TicketStatus::ASSIGNED, 'team_id' => $this->team->id]);
    Event::fake([TicketWorkStarted::class]);

    $service = app(TicketService::class);
    $service->startTicket($this->ticket, $this->worker);

    Event::assertDispatched(TicketWorkStarted::class, function ($event) {
        return $event->ticket->id === $this->ticket->id
            && $event->ticket->status === TicketStatus::IN_PROGRESS;
    });
});

test('TicketAccomplishmentService::accomplishTicket dispatches TicketAccomplished event', function () {
    Storage::fake('public');
    $this->ticket->update(['status' => TicketStatus::IN_PROGRESS, 'team_id' => $this->team->id]);
    Event::fake([TicketAccomplished::class]);

    $service = app(TicketAccomplishmentService::class);
    $service->accomplishTicket(
        $this->ticket,
        $this->worker,
        [
            'remarks' => 'Completed wire fix successfully',
            'consumer_name' => 'Maria Santos',
            'signature' => UploadedFile::fake()->image('signature.png'),
            'photos' => [UploadedFile::fake()->image('photo1.jpg')],
        ]
    );

    Event::assertDispatched(TicketAccomplished::class, function ($event) {
        return $event->ticket->id === $this->ticket->id
            && $event->ticket->status === TicketStatus::RESOLVED;
    });
});

test('TicketAccomplishmentService::verifyAccomplishment dispatches TicketVerifiedAndClosed when approved', function () {
    $this->ticket->update(['status' => TicketStatus::RESOLVED, 'team_id' => $this->team->id]);
    $accomplishment = TicketAccomplishment::create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->worker->id,
        'remarks' => 'Line restored',
        'status' => TicketAccomplishmentStatus::PENDING,
    ]);

    Event::fake([TicketVerifiedAndClosed::class]);

    $service = app(TicketAccomplishmentService::class);
    $service->verifyAccomplishment($this->ticket, $accomplishment, [
        'status' => TicketAccomplishmentStatus::APPROVED->value,
    ], $this->supervisor);

    Event::assertDispatched(TicketVerifiedAndClosed::class, function ($event) {
        return $event->ticket->id === $this->ticket->id
            && $event->ticket->status === TicketStatus::CLOSED;
    });
});

test('TicketAccomplishmentService::verifyAccomplishment dispatches TicketWorkStarted when rejected', function () {
    $this->ticket->update(['status' => TicketStatus::RESOLVED, 'team_id' => $this->team->id]);
    $accomplishment = TicketAccomplishment::create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->worker->id,
        'remarks' => 'Line restored',
        'status' => TicketAccomplishmentStatus::PENDING,
    ]);

    Event::fake([TicketWorkStarted::class]);

    $service = app(TicketAccomplishmentService::class);
    $service->verifyAccomplishment($this->ticket, $accomplishment, [
        'status' => TicketAccomplishmentStatus::REJECTED->value,
        'rejection_reason' => 'Photo evidence unclear, please re-inspect pole 14',
    ], $this->supervisor);

    Event::assertDispatched(TicketWorkStarted::class, function ($event) {
        return $event->ticket->id === $this->ticket->id
            && $event->ticket->status === TicketStatus::IN_PROGRESS;
    });
});

test('Livewire TicketStatusBadge renders and responds to status events', function () {
    Livewire::test(TicketStatusBadge::class, ['ticket' => $this->ticket])
        ->assertSee('Open')
        ->dispatch('echo-private:cwd.operations,.TicketAssigned', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200);
});

test('Livewire TicketAssignedPersonnel renders and updates on assignment events', function () {
    Livewire::test(TicketAssignedPersonnel::class, ['ticket' => $this->ticket])
        ->assertSee('Team not yet assigned')
        ->dispatch('echo-private:cwd.operations,.TicketAssigned', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200);
});

test('Livewire TicketOperationalControls dynamically updates actionable buttons on status progression', function () {
    $this->actingAs($this->cwdOfficer);

    // Initial state: Ticket is OPEN -> Cancel Ticket and Edit Details are visible, Force Close is NOT visible
    $test = Livewire::test(TicketOperationalControls::class, ['ticket' => $this->ticket])
        ->assertSee('Cancel Ticket')
        ->assertSee('Edit Details')
        ->assertDontSee('Force Close');

    // Simulate real-time TicketAssigned event from field dispatch
    $this->ticket->update(['status' => TicketStatus::ASSIGNED->value, 'team_id' => $this->team->id]);

    $test->dispatch('echo-private:cwd.operations,.TicketAssigned', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertDontSee('Cancel Ticket')
        ->assertDontSee('Edit Details')
        ->assertSee('Force Close');
});

test('Livewire TicketHierarchy renders origin and child status pills and updates in real time', function () {
    $this->actingAs($this->cwdOfficer);

    // Create child ticket linked to this->ticket
    $child = Ticket::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_number' => 'TKT-20261014-002',
        'parent_ticket_id' => $this->ticket->id,
        'status' => TicketStatus::OPEN->value,
        'department_id' => $this->ticket->department_id,
        'category_id' => $this->ticket->category_id,
        'reported_at' => now(),
    ]);

    $test = Livewire::test(TicketHierarchy::class, ['ticket' => $this->ticket])
        ->assertSee('Origin Ticket')
        ->assertSee('TKT-20261014-001 (Current)')
        ->assertSee('Child Tickets (1)')
        ->assertSee('TKT-20261014-002')
        ->assertSee('Open');

    // Simulate real-time status change on child ticket
    $child->update(['status' => TicketStatus::ASSIGNED->value, 'team_id' => $this->team->id]);

    $test->dispatch('echo-private:cwd.operations,.TicketAssigned', [
        'ticket_id' => $child->id,
        'parent_ticket_id' => $this->ticket->id,
        'status' => 'assigned',
    ])
        ->assertStatus(200)
        ->assertSee('Assigned');

    // Simulate creation of a second child ticket
    $secondChild = Ticket::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_number' => 'TKT-20261014-003',
        'parent_ticket_id' => $this->ticket->id,
        'status' => TicketStatus::OPEN->value,
        'department_id' => $this->ticket->department_id,
        'category_id' => $this->ticket->category_id,
        'reported_at' => now(),
    ]);

    $test->dispatch('echo-private:cwd.operations,.TicketCreated', [
        'ticket_id' => $secondChild->id,
        'parent_ticket_id' => $this->ticket->id,
        'status' => 'open',
    ])
        ->assertStatus(200)
        ->assertSee('Child Tickets (2)')
        ->assertSee('TKT-20261014-003');
});

test('Livewire TicketHistoryTabs renders all 4 real-time history tables and remarks thread', function () {
    $this->actingAs($this->cwdOfficer);

    Livewire::test(TicketHistoryTabs::class, ['ticket' => $this->ticket])
        ->assertSee('Status changes')
        ->assertSee('Team assignments')
        ->assertSee('Endorsement requests')
        ->assertSee('Field accomplishments')
        ->assertSee('Activity remarks')
        ->assertSee('No status changes yet')
        ->assertSee('No team assignments yet')
        ->assertSee('No endorsement requests')
        ->assertSee('No field reports submitted');
});

test('Livewire TicketHistoryTabs updates status and assignment tables in real time upon TicketAssigned', function () {
    $this->actingAs($this->cwdOfficer);

    $test = Livewire::test(TicketHistoryTabs::class, ['ticket' => $this->ticket])
        ->assertSee('No team assignments yet');

    // Simulate assignment mutation
    TicketAssignment::create([
        'ticket_id' => $this->ticket->id,
        'team_id' => $this->team->id,
        'assigned_by_id' => $this->supervisor->id,
    ]);
    TicketStatusLog::create([
        'ticket_id' => $this->ticket->id,
        'old_status' => 'open',
        'new_status' => 'assigned',
        'changed_by_id' => $this->supervisor->id,
    ]);
    $this->ticket->update(['status' => TicketStatus::ASSIGNED->value, 'team_id' => $this->team->id]);

    $test->dispatch('echo-private:cwd.operations,.TicketAssigned', [
        'ticket_id' => $this->ticket->id,
        'status' => 'assigned',
    ])
        ->assertStatus(200)
        ->assertSee('Crew Bravo')
        ->assertSee('Active Handover')
        ->assertDontSee('No team assignments yet');
});

test('Livewire TicketHistoryTabs updates field reports and status log in real time upon TicketAccomplished and TicketVerifiedAndClosed', function () {
    $this->actingAs($this->cwdOfficer);

    $test = Livewire::test(TicketHistoryTabs::class, ['ticket' => $this->ticket])
        ->assertSee('No field reports submitted');

    // Simulate accomplishment submission
    $acc = TicketAccomplishment::create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->worker->id,
        'remarks' => 'Restored damaged transformer tap line.',
        'status' => TicketAccomplishmentStatus::PENDING->value,
        'accomplished_at' => now(),
    ]);
    TicketStatusLog::create([
        'ticket_id' => $this->ticket->id,
        'old_status' => 'in_progress',
        'new_status' => 'resolved',
        'changed_by_id' => $this->worker->id,
    ]);
    $this->ticket->update(['status' => TicketStatus::RESOLVED->value]);

    $test->dispatch('echo-private:cwd.operations,.TicketAccomplished', [
        'ticket_id' => $this->ticket->id,
        'status' => 'resolved',
    ])
        ->assertStatus(200)
        ->assertSee('Restored damaged transformer tap line.')
        ->assertDontSee('No field reports submitted');

    // Simulate supervisor approval and closure
    $acc->update([
        'status' => TicketAccomplishmentStatus::APPROVED->value,
        'approved_by_id' => $this->supervisor->id,
    ]);
    TicketStatusLog::create([
        'ticket_id' => $this->ticket->id,
        'old_status' => 'resolved',
        'new_status' => 'closed',
        'changed_by_id' => $this->supervisor->id,
    ]);
    $this->ticket->update(['status' => TicketStatus::CLOSED->value, 'closed_at' => now()]);

    $test->dispatch('echo-private:cwd.operations,.TicketVerifiedAndClosed', [
        'ticket_id' => $this->ticket->id,
        'status' => 'closed',
    ])
        ->assertStatus(200)
        ->assertSee('Approved');
});

