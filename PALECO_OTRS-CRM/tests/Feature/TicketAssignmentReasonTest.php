<?php

use App\Enums\TicketStatus;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Services\Tickets\TicketService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['database.default' => 'assign_test', 'database.connections.assign_test' => [
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
        $table->string('password');
        $table->tinyInteger('is_active')->default(1);
        $table->timestamps();
    });

    Schema::create('teams', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('team_name');
        $table->string('team_desc')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->time('shift_start')->nullable();
        $table->time('shift_end')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('team_members', function (Blueprint $table) {
        $table->id();
        $table->string('team_id', 26);
        $table->string('user_id', 26);
        $table->foreignId('team_role_id')->nullable();
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
        $table->string('consumer_contact')->nullable();
        $table->string('complaint_source')->default('walk_in');
        $table->text('complaint_description')->nullable();
        $table->foreignId('category_id')->nullable();
        $table->tinyInteger('other_category')->default(0);
        $table->string('other_category_name')->nullable();
        $table->string('purok')->nullable();
        $table->string('street')->nullable();
        $table->string('barangay');
        $table->string('landmark')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->string('team_id', 26)->nullable();
        $table->string('created_by_id', 26)->nullable();
        $table->string('status')->default('open');
        $table->tinyInteger('is_offline_synced')->default(0);
        $table->timestamp('synced_at')->nullable();
        $table->timestamp('reported_at')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('client_started_at')->nullable();
        $table->timestamp('resolved_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->softDeletes();
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

    Schema::create('ticket_assignments', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('team_id', 26);
        $table->string('assigned_by_id', 26);
        $table->text('reason')->nullable();
        $table->timestamp('unassigned_at')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_endorsements', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_id', 26);
        $table->string('created_by_id', 26);
        $table->foreignId('suggested_department_id')->nullable();
        $table->text('reason');
        $table->string('status')->default('pending');
        $table->string('pre_endorsement_status');
        $table->text('rejection_reason')->nullable();
        $table->string('reviewed_by_id', 26)->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->timestamps();
    });

    // Seed roles and departments
    DB::table('account_roles')->insert([
        ['id' => 1, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor'],
        ['id' => 2, 'role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel'],
    ]);

    DB::table('departments')->insert([
        ['id' => 1, 'dept_name' => 'Technical Services Department'],
    ]);

    DB::table('teams')->insert([
        ['id' => '01m4teamalpha00000000000001', 'team_name' => 'Alpha Crew', 'department_id' => 1],
        ['id' => '01m4teambravo00000000000002', 'team_name' => 'Bravo Crew', 'department_id' => 1],
    ]);

    DB::table('users')->insert([
        [
            'id' => '01m4usersup0000000000000001',
            'username' => 'supervisor_mycka',
            'first_name' => 'Mycka',
            'last_name' => 'Supervisor',
            'contact' => '09123456789',
            'role_id' => 1,
            'department_id' => 1,
            'password' => bcrypt('password'),
            'is_active' => 1,
        ],
    ]);
});

afterEach(function () {
    DB::purge('assign_test');
});

test('activeAssignment relationship resolves the latest active assignment record', function () {
    $ticketId = '01m4tkt0000000000000000001';
    DB::table('tickets')->insert([
        'id' => $ticketId,
        'ticket_number' => 'TKT-261011-001',
        'barangay' => 'Poblacion',
        'status' => 'assigned',
        'department_id' => 1,
        'team_id' => '01m4teambravo00000000000002',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Initial assignment: Alpha Crew, now closed
    DB::table('ticket_assignments')->insert([
        'id' => 1,
        'ticket_id' => $ticketId,
        'team_id' => '01m4teamalpha00000000000001',
        'assigned_by_id' => '01m4usersup0000000000000001',
        'reason' => 'Initial dispatch',
        'unassigned_at' => now()->subHour(),
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHour(),
    ]);

    // Reassignment: Bravo Crew, currently active
    DB::table('ticket_assignments')->insert([
        'id' => 2,
        'ticket_id' => $ticketId,
        'team_id' => '01m4teambravo00000000000002',
        'assigned_by_id' => '01m4usersup0000000000000001',
        'reason' => 'Alpha truck broke down, reassigned to Bravo crew',
        'unassigned_at' => null,
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    $ticket = Ticket::with('activeAssignment.team')->find($ticketId);

    expect($ticket->activeAssignment)->not->toBeNull()
        ->and($ticket->activeAssignment->id)->toBe(2)
        ->and($ticket->activeAssignment->team_id)->toBe('01m4teambravo00000000000002')
        ->and($ticket->activeAssignment->reason)->toBe('Alpha truck broke down, reassigned to Bravo crew');
});

test('TicketService::assignTicket persists and reloads active assignment with reason', function () {
    $supervisor = User::with('role')->find('01m4usersup0000000000000001');

    $ticketId = '01m4tkt0000000000000000002';
    DB::table('tickets')->insert([
        'id' => $ticketId,
        'ticket_number' => 'TKT-261011-002',
        'barangay' => 'San Pedro',
        'status' => 'open',
        'department_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ticket = Ticket::find($ticketId);
    $service = app(TicketService::class);

    $updatedTicket = $service->assignTicket(
        $ticket,
        '01m4teamalpha00000000000001',
        $supervisor,
        'Emergency response for burning fuse cutout'
    );

    expect($updatedTicket->status)->toBe(TicketStatus::ASSIGNED)
        ->and($updatedTicket->team_id)->toBe('01m4teamalpha00000000000001')
        ->and($updatedTicket->activeAssignment)->not->toBeNull()
        ->and($updatedTicket->activeAssignment->reason)->toBe('Emergency response for burning fuse cutout')
        ->and($updatedTicket->activeAssignment->assigned_by_id)->toBe('01m4usersup0000000000000001');
});

test('getDetailedTicketMobile eager loads active assignment and reason', function () {
    $ticketId = '01m4tkt0000000000000000003';
    DB::table('tickets')->insert([
        'id' => $ticketId,
        'ticket_number' => 'TKT-261011-003',
        'barangay' => 'Tiniguiban',
        'status' => 'assigned',
        'department_id' => 1,
        'team_id' => '01m4teamalpha00000000000001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ticket_assignments')->insert([
        'id' => 10,
        'ticket_id' => $ticketId,
        'team_id' => '01m4teamalpha00000000000001',
        'assigned_by_id' => '01m4usersup0000000000000001',
        'reason' => 'Line patrol required on feeder 3',
        'unassigned_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ticket = Ticket::find($ticketId);
    $service = app(TicketService::class);
    $detailed = $service->getDetailedTicketMobile($ticket);

    expect($detailed->relationLoaded('activeAssignment'))->toBeTrue()
        ->and($detailed->activeAssignment)->not->toBeNull()
        ->and($detailed->activeAssignment->reason)->toBe('Line patrol required on feeder 3')
        ->and($detailed->activeAssignment->assigner->full_name)->toBe('Mycka Supervisor');
});
