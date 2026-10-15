<?php

use App\Enums\TicketStatus;
use App\Events\TicketCancelled;
use App\Events\TicketForceClosed;
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
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Services\Tickets\TicketService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

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
        $table->text('cancellation_reason')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->text('closure_reason')->nullable();
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
    $dept = Department::create(['dept_name' => 'Line Maintenance']);
    $category = TicketCategory::create(['category_name' => 'Voltage Fluctuation']);

    $this->cwdOfficer = User::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'username' => 'cwd_officer_jane',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'role_id' => $cwdRole->id,
    ]);

    $this->supervisor = User::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'username' => 'sup_robert',
        'first_name' => 'Robert',
        'last_name' => 'Smith',
        'role_id' => $supRole->id,
        'department_id' => $dept->id,
    ]);

    $this->team = Team::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'team_name' => 'Emergency Alpha',
        'department_id' => $dept->id,
    ]);

    $this->ticket = Ticket::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ticket_number' => 'TKT-20261014-040',
        'status' => TicketStatus::OPEN->value,
        'department_id' => $dept->id,
        'category_id' => $category->id,
        'reported_at' => now(),
    ]);
});

test('TicketCancelled event broadcasts on cwd.operations with complete terminal payload', function () {
    $this->ticket->update([
        'status' => TicketStatus::CANCELLED->value,
        'cancellation_reason' => 'Customer reported duplicate complaint.',
        'cancelled_at' => now(),
    ]);

    $event = new TicketCancelled($this->ticket);

    $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();
    expect($channels)->toContain('private-cwd.operations')
        ->and($event->broadcastAs())->toBe('TicketCancelled')
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $this->ticket->id,
            'ticket_number' => 'TKT-20261014-040',
            'status' => 'cancelled',
            'status_label' => 'Cancelled',
            'cancellation_reason' => 'Customer reported duplicate complaint.',
        ]);
});

test('TicketForceClosed event broadcasts on cwd.operations with complete force-closure payload', function () {
    $this->ticket->update([
        'status' => TicketStatus::CLOSED->value,
        'is_force_closed' => true,
        'closure_reason' => 'Substation grid restored by NGCP directly.',
        'closed_by_id' => $this->cwdOfficer->id,
        'closed_at' => now(),
    ]);

    $event = new TicketForceClosed($this->ticket);

    $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();
    expect($channels)->toContain('private-cwd.operations')
        ->and($event->broadcastAs())->toBe('TicketForceClosed')
        ->and($event->broadcastWith())->toMatchArray([
            'ticket_id' => $this->ticket->id,
            'ticket_number' => 'TKT-20261014-040',
            'status' => 'closed',
            'status_label' => 'Closed',
            'is_force_closed' => true,
            'closure_reason' => 'Substation grid restored by NGCP directly.',
            'closed_by' => 'Jane Doe',
        ]);
});

test('TicketService::cancelTicket dispatches TicketCancelled broadcast event', function () {
    Event::fake([TicketCancelled::class]);

    $service = app(TicketService::class);
    $service->cancelTicket($this->ticket, [
        'cancellation_reason' => 'Caller resolved issue independently.',
    ], $this->cwdOfficer);

    Event::assertDispatched(TicketCancelled::class, function ($event) {
        return $event->ticket->id === $this->ticket->id
            && $event->ticket->status === TicketStatus::CANCELLED
            && $event->ticket->cancellation_reason === 'Caller resolved issue independently.';
    });
});

test('TicketService::forceCloseTicket dispatches TicketForceClosed broadcast event', function () {
    // Ticket must be in an active assigned status to force-close
    $this->ticket->update([
        'status' => TicketStatus::ASSIGNED->value,
        'team_id' => $this->team->id,
    ]);

    Event::fake([TicketForceClosed::class]);

    $service = app(TicketService::class);
    $service->forceCloseTicket($this->ticket, [
        'closure_reason' => 'Executive directive due to system-wide feeder maintenance.',
    ], $this->cwdOfficer);

    Event::assertDispatched(TicketForceClosed::class, function ($event) {
        return $event->ticket->id === $this->ticket->id
            && $event->ticket->status === TicketStatus::CLOSED
            && $event->ticket->is_force_closed === true
            && $event->ticket->closure_reason === 'Executive directive due to system-wide feeder maintenance.';
    });
});

test('TicketStatusBadge Livewire component reacts to TicketCancelled and TicketForceClosed', function () {
    $component = Livewire::test(TicketStatusBadge::class, ['ticket' => $this->ticket])
        ->assertSee('Open');

    // Simulate cancellation
    $this->ticket->update(['status' => TicketStatus::CANCELLED->value]);
    $component->dispatch('echo-private:cwd.operations,.TicketCancelled', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Cancelled');

    // Simulate force closure
    $this->ticket->update(['status' => TicketStatus::CLOSED->value, 'is_force_closed' => true]);
    $component->dispatch('echo-private:cwd.operations,.TicketForceClosed', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Closed');
});

test('TicketOperationalControls Livewire component mounts cancellation notice and unmounts action buttons upon TicketCancelled', function () {
    $this->actingAs($this->cwdOfficer);

    $component = Livewire::test(TicketOperationalControls::class, ['ticket' => $this->ticket])
        ->assertSee('Cancel Ticket')
        ->assertSee('Edit Details')
        ->assertDontSee('Service Ticket Cancelled');

    // Mutate state to cancelled
    $this->ticket->update([
        'status' => TicketStatus::CANCELLED->value,
        'cancellation_reason' => 'Invalid consumer meter identifier.',
        'cancelled_at' => now(),
    ]);

    $component->dispatch('echo-private:cwd.operations,.TicketCancelled', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertDontSee('Cancel Ticket')
        ->assertDontSee('Edit Details')
        ->assertSee('Service Ticket Cancelled')
        ->assertSee('Invalid consumer meter identifier.');
});

test('TicketOperationalControls Livewire component mounts administrative force closure notice upon TicketForceClosed', function () {
    $this->actingAs($this->cwdOfficer);

    $this->ticket->update(['status' => TicketStatus::ASSIGNED->value, 'team_id' => $this->team->id]);

    $component = Livewire::test(TicketOperationalControls::class, ['ticket' => $this->ticket])
        ->assertSee('Force Close')
        ->assertDontSee('Administrative Force Closure');

    // Mutate state to force closed
    $this->ticket->update([
        'status' => TicketStatus::CLOSED->value,
        'is_force_closed' => true,
        'closure_reason' => 'Work overtaken by internal substation crew.',
        'closed_by_id' => $this->cwdOfficer->id,
        'closed_at' => now(),
    ]);

    $component->dispatch('echo-private:cwd.operations,.TicketForceClosed', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertDontSee('Force Close')
        ->assertSee('Administrative Force Closure')
        ->assertSee('Work overtaken by internal substation crew.')
        ->assertSee('Jane Doe');
});

test('TicketHierarchy Livewire component updates status pill and suppresses New Child button on terminal status events', function () {
    $this->actingAs($this->cwdOfficer);

    $component = Livewire::test(TicketHierarchy::class, ['ticket' => $this->ticket])
        ->assertSee('Open')
        ->assertSee('New Child');

    // Dispatch cancellation
    $this->ticket->update(['status' => TicketStatus::CANCELLED->value]);
    $component->dispatch('echo-private:cwd.operations,.TicketCancelled', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Cancelled')
        ->assertDontSee('New Child');

    // Dispatch force closure
    $this->ticket->update(['status' => TicketStatus::CLOSED->value, 'is_force_closed' => true]);
    $component->dispatch('echo-private:cwd.operations,.TicketForceClosed', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Closed')
        ->assertDontSee('New Child');
});

test('TicketHistoryTabs Livewire component logs terminal status transitions in real time', function () {
    $this->actingAs($this->cwdOfficer);

    $component = Livewire::test(TicketHistoryTabs::class, ['ticket' => $this->ticket])
        ->assertSee('No status changes yet');

    // Simulate force closure transition in status log
    TicketStatusLog::create([
        'ticket_id' => $this->ticket->id,
        'old_status' => 'assigned',
        'new_status' => 'closed',
        'changed_by_id' => $this->cwdOfficer->id,
    ]);
    $this->ticket->update([
        'status' => TicketStatus::CLOSED->value,
        'is_force_closed' => true,
    ]);

    $component->dispatch('echo-private:cwd.operations,.TicketForceClosed', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Force Closed')
        ->assertDontSee('No status changes yet');
});

test('TicketAssignedPersonnel Livewire component unassigns crew upon TicketForceClosed', function () {
    $this->ticket->update(['team_id' => $this->team->id]);

    $component = Livewire::test(TicketAssignedPersonnel::class, ['ticket' => $this->ticket])
        ->assertSee('Emergency Alpha');

    // Force close sets team_id to null
    $this->ticket->update(['team_id' => null, 'status' => TicketStatus::CLOSED->value, 'is_force_closed' => true]);

    $component->dispatch('echo-private:cwd.operations,.TicketForceClosed', ['ticket_id' => $this->ticket->id])
        ->assertStatus(200)
        ->assertSee('Team not yet assigned');
});
