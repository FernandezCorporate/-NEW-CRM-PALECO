<?php

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Events\TicketEndorsementDecided;
use App\Events\TicketEndorsementInitiated;
use App\Livewire\Shared\TicketAssignedPersonnel;
use App\Livewire\Shared\TicketHierarchy;
use App\Livewire\Shared\TicketHistoryTabs;
use App\Livewire\Shared\TicketOperationalControls;
use App\Livewire\Shared\TicketStatusBadge;
use App\Livewire\Shared\TicketTable;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\TicketEndorsement;
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Services\Tickets\TicketEndorsementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    config(['database.default' => 'phase5_test', 'database.connections.phase5_test' => [
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
        $table->foreignId('team_role_id')->nullable();
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
        $table->foreignId('department_id')->nullable();
        $table->string('team_id', 26)->nullable();
        $table->string('consumer_id', 26)->nullable();
        $table->string('consumer_name')->nullable();
        $table->string('consumer_contact')->nullable();
        $table->string('complaint_source')->nullable();
        $table->text('complaint_description')->nullable();
        $table->foreignId('category_id')->nullable();
        $table->boolean('other_category')->default(false);
        $table->string('other_category_name')->nullable();
        $table->string('purok')->nullable();
        $table->string('street')->nullable();
        $table->string('barangay')->nullable();
        $table->string('landmark')->nullable();
        $table->string('subject')->nullable();
        $table->string('status')->default('open');
        $table->string('priority')->default('medium');
        $table->string('created_by_id', 26)->nullable();
        $table->string('closed_by_id', 26)->nullable();
        $table->timestamp('reported_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->text('cancellation_reason')->nullable();
        $table->text('closure_reason')->nullable();
        $table->boolean('is_force_closed')->default(false);
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('ticket_assignments', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('team_id', 26);
        $table->string('assigned_by_id', 26)->nullable();
        $table->timestamp('unassigned_at')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_status_logs', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('changed_by_id', 26)->nullable();
        $table->string('old_status')->nullable();
        $table->string('new_status');
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
        $table->timestamp('accomplished_at')->nullable();
        $table->string('consumer_name')->nullable();
        $table->string('signature_path')->nullable();
        $table->string('status', 30)->default('pending');
        $table->string('approved_by_id', 26)->nullable();
        $table->string('rejected_by_id', 26)->nullable();
        $table->text('rejection_reason')->nullable();
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

    // Seed roles and actors
    $cwdRole = AccountRole::create(['role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);
    $supRole = AccountRole::create(['role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);

    $this->deptSource = Department::create(['dept_name' => 'Line Maintenance']);
    $this->deptTarget = Department::create(['dept_name' => 'Substation Engineering']);
    $category = TicketCategory::create(['category_name' => 'High Voltage Fault']);

    $this->cwdOfficer = User::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'username' => 'cwd_dispatcher_amy',
        'first_name' => 'Amy',
        'last_name' => 'Adams',
        'role_id' => $cwdRole->id,
    ]);

    $this->supervisor = User::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'username' => 'sup_carlos',
        'first_name' => 'Carlos',
        'last_name' => 'Vance',
        'role_id' => $supRole->id,
        'department_id' => $this->deptSource->id,
    ]);

    $this->team = Team::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'team_name' => 'Crew Delta',
        'department_id' => $this->deptSource->id,
    ]);

    $this->ticket = Ticket::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_number' => 'TKT-20261014-050',
        'status' => TicketStatus::IN_PROGRESS->value,
        'department_id' => $this->deptSource->id,
        'team_id' => $this->team->id,
        'category_id' => $category->id,
        'consumer_name' => 'John Doe',
        'consumer_contact' => '09171234567',
        'complaint_source' => 'phone_call',
        'complaint_description' => 'Feeder trip requiring transformer replacement by Substation.',
        'purok' => 'Purok 1',
        'street' => 'Rizal Ave',
        'barangay' => 'San Pedro',
        'reported_at' => now(),
    ]);

    TicketAssignment::create([
        'ticket_id' => $this->ticket->id,
        'team_id' => $this->team->id,
        'assigned_by_id' => $this->supervisor->id,
    ]);
});

test('TicketEndorsementInitiated event broadcasts on cwd.operations with complete initiation payload', function () {
    $endorsement = TicketEndorsement::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->deptTarget->id,
        'reason' => 'Transformer fault beyond line crew scope; requires substation engineer.',
        'pre_endorsement_status' => TicketStatus::IN_PROGRESS->value,
        'status' => EndorsementStatus::PENDING->value,
    ]);

    $endorsement->load(['ticket', 'suggestedDepartment', 'creator']);

    $event = new TicketEndorsementInitiated($endorsement);

    expect($event->broadcastOn()->name)->toBe('private-cwd.operations')
        ->and($event->broadcastAs())->toBe('TicketEndorsementInitiated')
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $this->ticket->id,
            'endorsement_id' => $endorsement->id,
            'status' => 'pending_endorsement',
            'status_label' => 'Pending Endorsement',
            'suggested_department_id' => $this->deptTarget->id,
            'suggested_department_name' => 'Substation Engineering',
            'reason' => 'Transformer fault beyond line crew scope; requires substation engineer.',
            'requested_by' => 'Carlos Vance',
        ]);
});

test('TicketEndorsementDecided event broadcasts on cwd.operations with complete approval payload', function () {
    $this->ticket->update(['status' => TicketStatus::ENDORSED->value]);

    $endorsement = TicketEndorsement::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->deptTarget->id,
        'reason' => 'Transformer fault beyond line crew scope.',
        'pre_endorsement_status' => TicketStatus::IN_PROGRESS->value,
        'status' => EndorsementStatus::APPROVED->value,
        'reviewed_by_id' => $this->cwdOfficer->id,
        'reviewed_at' => now(),
    ]);

    $endorsement->load(['ticket', 'reviewer', 'suggestedDepartment']);

    $event = new TicketEndorsementDecided($endorsement, 'child-ticket-ulid', 'TKT-20261014-050-01');

    expect($event->broadcastOn()->name)->toBe('private-cwd.operations')
        ->and($event->broadcastAs())->toBe('TicketEndorsementDecided')
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $this->ticket->id,
            'endorsement_id' => $endorsement->id,
            'decision' => 'approved',
            'decision_label' => 'Approved',
            'parent_status' => 'endorsed',
            'parent_status_label' => 'Endorsed',
            'child_ticket_id' => 'child-ticket-ulid',
            'child_ticket_number' => 'TKT-20261014-050-01',
            'reviewed_by' => 'Amy Adams',
        ]);
});

test('TicketEndorsementDecided event broadcasts on cwd.operations with complete rejection payload', function () {
    $endorsement = TicketEndorsement::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->deptTarget->id,
        'reason' => 'Transformer fault beyond line crew scope.',
        'pre_endorsement_status' => TicketStatus::IN_PROGRESS->value,
        'status' => EndorsementStatus::REJECTED->value,
        'rejection_reason' => 'Primary fuse check must be completed before substation dispatch.',
        'reviewed_by_id' => $this->cwdOfficer->id,
        'reviewed_at' => now(),
    ]);

    $endorsement->load(['ticket', 'reviewer', 'suggestedDepartment']);

    $event = new TicketEndorsementDecided($endorsement);

    expect($event->broadcastOn()->name)->toBe('private-cwd.operations')
        ->and($event->broadcastAs())->toBe('TicketEndorsementDecided')
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $this->ticket->id,
            'endorsement_id' => $endorsement->id,
            'decision' => 'rejected',
            'decision_label' => 'Rejected',
            'rejection_reason' => 'Primary fuse check must be completed before substation dispatch.',
            'child_ticket_id' => null,
            'child_ticket_number' => null,
            'reviewed_by' => 'Amy Adams',
        ]);
});

test('TicketEndorsementService::requestEndorsement dispatches TicketEndorsementInitiated broadcast event', function () {
    Event::fake([TicketEndorsementInitiated::class]);

    $service = app(TicketEndorsementService::class);
    $endorsement = $service->requestEndorsement($this->ticket, [
        'suggested_department_id' => $this->deptTarget->id,
        'reason' => 'Line crew determined substation transformers need repair.',
    ], $this->supervisor);

    $this->ticket->refresh();
    expect($this->ticket->status)->toBe(TicketStatus::PENDING_ENDORSEMENT);

    Event::assertDispatched(TicketEndorsementInitiated::class, function ($event) use ($endorsement) {
        return $event->endorsement->id === $endorsement->id
            && $event->endorsement->ticket_id === $this->ticket->id
            && $event->endorsement->suggested_department_id === $this->deptTarget->id;
    });
});

test('TicketEndorsementService::verifyEndorsement approval spawns child ticket and dispatches TicketCreated and TicketEndorsementDecided', function () {
    Auth::login($this->cwdOfficer);

    $endorsement = TicketEndorsement::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->deptTarget->id,
        'reason' => 'Substation crew intervention needed.',
        'pre_endorsement_status' => TicketStatus::IN_PROGRESS->value,
        'status' => EndorsementStatus::PENDING->value,
    ]);

    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT->value]);

    Event::fake([TicketCreated::class, TicketEndorsementDecided::class]);

    $service = app(TicketEndorsementService::class);
    $result = $service->verifyEndorsement([
        'status' => EndorsementStatus::APPROVED->value,
        'department_id' => $this->deptTarget->id,
    ], $endorsement);

    $this->ticket->refresh();
    expect($result['success'])->toBe(true)
        ->and($this->ticket->status)->toBe(TicketStatus::ENDORSED)
        ->and($this->ticket->team_id)->toBeNull();

    // Assignment unassigned_at should be closed
    $assignment = TicketAssignment::where('ticket_id', $this->ticket->id)->first();
    expect($assignment->unassigned_at)->not->toBeNull();

    // Child ticket should be created for deptTarget
    $childTicket = Ticket::where('parent_ticket_id', $this->ticket->id)->first();
    expect($childTicket)->not->toBeNull()
        ->and($childTicket->department_id)->toBe($this->deptTarget->id)
        ->and($childTicket->status)->toBe(TicketStatus::OPEN);

    Event::assertDispatched(TicketCreated::class, function ($event) use ($childTicket) {
        return $event->ticket->id === $childTicket->id;
    });

    Event::assertDispatched(TicketEndorsementDecided::class, function ($event) use ($endorsement, $childTicket) {
        return $event->endorsement->id === $endorsement->id
            && $event->childTicketId === $childTicket->id
            && $event->childTicketNumber === $childTicket->ticket_number;
    });
});

test('TicketEndorsementService::verifyEndorsement rejection reverts parent ticket and dispatches TicketEndorsementDecided', function () {
    Auth::login($this->cwdOfficer);

    $endorsement = TicketEndorsement::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->deptTarget->id,
        'reason' => 'Substation crew intervention needed.',
        'pre_endorsement_status' => TicketStatus::IN_PROGRESS->value,
        'status' => EndorsementStatus::PENDING->value,
    ]);

    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT->value]);

    Event::fake([TicketCreated::class, TicketEndorsementDecided::class]);

    $service = app(TicketEndorsementService::class);
    $result = $service->verifyEndorsement([
        'status' => EndorsementStatus::REJECTED->value,
        'rejection_reason' => 'Must verify secondary distribution lines first.',
    ], $endorsement);

    $this->ticket->refresh();
    expect($result['success'])->toBe(true)
        ->and($this->ticket->status)->toBe(TicketStatus::IN_PROGRESS)
        ->and($this->ticket->team_id)->toBe($this->team->id);

    Event::assertNotDispatched(TicketCreated::class);

    Event::assertDispatched(TicketEndorsementDecided::class, function ($event) use ($endorsement) {
        return $event->endorsement->id === $endorsement->id
            && $event->childTicketId === null;
    });
});

test('TicketStatusBadge Livewire component reacts to TicketEndorsementInitiated and TicketEndorsementDecided', function () {
    $component = Livewire::test(TicketStatusBadge::class, ['ticket' => $this->ticket])
        ->assertSee('In Progress');

    // Simulate endorsement initiation
    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT->value]);
    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementInitiated', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Pending Endorsement');

    // Simulate endorsement approval
    $this->ticket->update(['status' => TicketStatus::ENDORSED->value]);
    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementDecided', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Endorsed');
});

test('TicketOperationalControls Livewire component suppresses action buttons during pending endorsement', function () {
    $this->actingAs($this->cwdOfficer);

    // Initial state: Open ticket has Edit, Create Child, and Cancel buttons
    $this->ticket->update(['status' => TicketStatus::OPEN->value, 'team_id' => null]);

    $component = Livewire::test(TicketOperationalControls::class, ['ticket' => $this->ticket])
        ->assertSee('Operational Controls')
        ->assertSee('Cancel Ticket')
        ->assertSee('Edit Details');

    // Transition to PENDING_ENDORSEMENT: All actions must be suppressed
    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT->value]);

    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementInitiated', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertDontSee('Operational Controls')
        ->assertDontSee('Cancel Ticket')
        ->assertDontSee('Edit Details');
});

test('TicketHierarchy Livewire component updates badge on TicketEndorsementInitiated and displays spawned child on approval', function () {
    $this->actingAs($this->cwdOfficer);

    $component = Livewire::test(TicketHierarchy::class, ['ticket' => $this->ticket])
        ->assertSee('In Progress');

    // Initiation
    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT->value]);
    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementInitiated', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Pending Endorsement');

    // Approval: child ticket created
    $child = Ticket::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_number' => 'TKT-20261014-050-01',
        'parent_ticket_id' => $this->ticket->id,
        'department_id' => $this->deptTarget->id,
        'status' => TicketStatus::OPEN->value,
        'subject' => 'Substation Followup',
        'reported_at' => now(),
    ]);

    $this->ticket->update(['status' => TicketStatus::ENDORSED->value]);

    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementDecided', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Endorsed')
        ->assertSee('TKT-20261014-050-01');
});

test('TicketHistoryTabs Livewire component updates endorsement table and counter upon TicketEndorsementInitiated and TicketEndorsementDecided', function () {
    $this->actingAs($this->cwdOfficer);

    $component = Livewire::test(TicketHistoryTabs::class, ['ticket' => $this->ticket])
        ->assertSee('No endorsement requests');

    // Add endorsement record
    $endorsement = TicketEndorsement::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->deptTarget->id,
        'reason' => 'Requires specialized transformer crew.',
        'pre_endorsement_status' => TicketStatus::IN_PROGRESS->value,
        'status' => EndorsementStatus::PENDING->value,
    ]);

    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT->value]);

    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementInitiated', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertDontSee('No endorsement requests')
        ->assertSee('Substation Engineering')
        ->assertSee('Carlos Vance');

    // Decide endorsement (Approved)
    $endorsement->update([
        'status' => EndorsementStatus::APPROVED->value,
        'reviewed_by_id' => $this->cwdOfficer->id,
        'reviewed_at' => now(),
    ]);

    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementDecided', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Approved');
});

test('TicketAssignedPersonnel Livewire component unassigns crew upon endorsement approval', function () {
    $component = Livewire::test(TicketAssignedPersonnel::class, ['ticket' => $this->ticket])
        ->assertSee('Crew Delta');

    // Endorsement approval clears team_id and status becomes ENDORSED
    $this->ticket->update([
        'status' => TicketStatus::ENDORSED->value,
        'team_id' => null,
    ]);

    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementDecided', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Team not yet assigned');
});

test('TicketTable Livewire component re-renders rows on TicketEndorsementInitiated and TicketEndorsementDecided', function () {
    $this->actingAs($this->cwdOfficer);

    $component = Livewire::test(TicketTable::class, ['context' => 'cwd'])
        ->assertSee('TKT-20261014-050')
        ->assertSee('In Progress');

    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT->value]);

    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementInitiated', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Pending Endorsement');

    $this->ticket->update(['status' => TicketStatus::ENDORSED->value]);

    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementDecided', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Endorsed');
});

test('DashboardOverview Livewire component refreshes overview metrics on TicketEndorsementInitiated and TicketEndorsementDecided', function () {
    $this->actingAs($this->cwdOfficer);

    $component = Livewire::test(\App\Livewire\Cwd\DashboardOverview::class);

    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT->value]);

    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementInitiated', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200);

    $this->ticket->update(['status' => TicketStatus::ENDORSED->value]);

    $component->dispatch('echo-private:cwd.operations,.TicketEndorsementDecided', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200);
});
