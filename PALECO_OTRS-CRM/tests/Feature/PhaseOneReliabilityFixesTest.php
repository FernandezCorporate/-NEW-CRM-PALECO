<?php

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Http\Controllers\Api\Concerns\ResolvesClientTimestamp;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketEndorsement;
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Services\Tickets\TicketEndorsementService;
use App\Services\Tickets\TicketService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Dummy class to test ResolvesClientTimestamp trait directly
class TestTimestampResolverController
{
    use ResolvesClientTimestamp;

    public function testResolve(Request $request, Ticket $ticket): ?Carbon
    {
        return $this->resolveClientTimestamp($request, $ticket);
    }
}

beforeEach(function () {
    config(['database.default' => 'phase1_test', 'database.connections.phase1_test' => [
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
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_assignments', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('team_id', 26);
        $table->string('assigned_by_id', 26);
        $table->text('reason')->nullable();
        $table->timestamp('assigned_at')->nullable();
        $table->timestamp('unassigned_at')->nullable();
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

    Schema::create('ticket_endorsements', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_id', 26);
        $table->string('created_by_id', 26);
        $table->foreignId('suggested_department_id')->nullable();
        $table->text('reason');
        $table->string('pre_endorsement_status');
        $table->string('status')->default('pending');
        $table->string('reviewed_by_id', 26)->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->text('rejection_reason')->nullable();
        $table->timestamps();
    });
});

test('clock drift: accepts timestamp within 3-minute grace window and clamps safely to ticket creation', function () {
    Carbon::setTestNow('2026-10-13 14:02:00');

    $ticket = Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-001',
        'barangay' => 'San Pedro',
        'reported_at' => Carbon::parse('2026-10-13 14:00:00'),
        'created_at' => Carbon::parse('2026-10-13 14:00:00'),
    ]);

    $controller = new TestTimestampResolverController();

    // Lineman phone was 2 minutes behind ticket creation time (13:58:00)
    $driftTimestamp = Carbon::parse('2026-10-13 13:58:00')->toIso8601String();
    $request = new Request([], [], [], [], [], [], json_encode([]));
    $request->headers->set('X-Client-Timestamp', $driftTimestamp);

    $resolved = $controller->testResolve($request, $ticket);

    // Should not throw validation exception, and clamped to reported_at (14:00:00)
    expect($resolved)->not->toBeNull();
    expect($resolved->toIso8601String())->toBe($ticket->reported_at->toIso8601String());

    Carbon::setTestNow();
});

test('clock drift: rejects timestamp exceeding 3-minute backward window', function () {
    Carbon::setTestNow('2026-10-13 14:02:00');

    $ticket = Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-002',
        'barangay' => 'San Pedro',
        'reported_at' => Carbon::parse('2026-10-13 14:00:00'),
        'created_at' => Carbon::parse('2026-10-13 14:00:00'),
    ]);

    $controller = new TestTimestampResolverController();

    // Lineman phone timestamp is 5 minutes earlier than ticket creation
    $excessiveDrift = Carbon::parse('2026-10-13 13:55:00')->toIso8601String();
    $request = new Request();
    $request->headers->set('X-Client-Timestamp', $excessiveDrift);

    expect(fn () => $controller->testResolve($request, $ticket))
        ->toThrow(ValidationException::class);

    Carbon::setTestNow();
});

test('child ticket generator creates consecutive suffixes under parent ticket', function () {
    $parentTicket = Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-001',
        'barangay' => 'San Pedro',
        'reported_at' => now(),
    ]);

    $service = app(TicketService::class);

    $child1Number = $service->generateChildTicketNumber($parentTicket);
    expect($child1Number)->toBe('TKT-261013-001-1');

    // Create child 1
    Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-001-1',
        'parent_ticket_id' => $parentTicket->id,
        'barangay' => 'San Pedro',
    ]);

    $child2Number = $service->generateChildTicketNumber($parentTicket);
    expect($child2Number)->toBe('TKT-261013-001-2');
});

test('rejected endorsement gracefully reverts to OPEN if assigned team was soft deleted', function () {
    $user = User::forceCreate([
        'id' => (string) Str::ulid(),
        'username' => 'supervisor_cwd',
        'first_name' => 'John',
        'last_name' => 'Supervisor',
        'contact' => '09171234567',
        'password' => 'secret',
    ]);

    Auth::login($user);

    $dept = Department::forceCreate([
        'id' => 1,
        'dept_name' => 'Technical Department',
    ]);

    $team = Team::forceCreate([
        'id' => (string) Str::ulid(),
        'team_name' => 'Crew Delta',
        'department_id' => 1,
    ]);

    $ticket = Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-010',
        'barangay' => 'San Pedro',
        'department_id' => 1,
        'team_id' => $team->id,
        'status' => TicketStatus::ASSIGNED,
    ]);

    $ticket->assignments()->create([
        'team_id' => $team->id,
        'assigned_by_id' => $user->id,
        'assigned_at' => now(),
    ]);

    // Endorsement requested: ticket becomes pending_endorsement
    $endorsement = TicketEndorsement::forceCreate([
        'ticket_id' => $ticket->id,
        'created_by_id' => $user->id,
        'reason' => 'Requires meter replacement',
        'pre_endorsement_status' => TicketStatus::ASSIGNED->value,
        'status' => EndorsementStatus::PENDING->value,
    ]);
    $ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT]);

    // Now, team is soft-deleted while endorsement is pending review
    $team->delete();
    expect(Team::where('id', $team->id)->exists())->toBeFalse();

    // Reviewer rejects the endorsement
    $endorsementService = app(TicketEndorsementService::class);
    $result = $endorsementService->verifyEndorsement([
        'status' => EndorsementStatus::REJECTED->value,
        'rejection_reason' => 'Invalid endorsement request',
    ], $endorsement);

    expect($result['success'])->toBeTrue();

    // Verify parent ticket fell back to OPEN and unassigned
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::OPEN);
    expect($ticket->team_id)->toBeNull();

    // Verify previous assignment was closed
    $activeAssignments = $ticket->assignments()->whereNull('unassigned_at')->count();
    expect($activeAssignments)->toBe(0);

    // Verify fallback remark was recorded
    $remarks = $ticket->remarks()->where('is_internal', true)->get();
    expect($remarks->count())->toBeGreaterThanOrEqual(1);
    expect($remarks->first()->body)->toContain('previously assigned team is no longer active');
});

test('rejected endorsement restores ASSIGNED status if team is still active', function () {
    $user = User::forceCreate([
        'id' => (string) Str::ulid(),
        'username' => 'supervisor_cwd_2',
        'first_name' => 'Jane',
        'last_name' => 'Supervisor',
        'contact' => '09171234568',
        'password' => 'secret',
    ]);

    Auth::login($user);

    $dept = Department::forceCreate([
        'id' => 2,
        'dept_name' => 'Maintenance Department',
    ]);

    $team = Team::forceCreate([
        'id' => (string) Str::ulid(),
        'team_name' => 'Crew Echo',
        'department_id' => 2,
    ]);

    $ticket = Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-011',
        'barangay' => 'San Pedro',
        'department_id' => 2,
        'team_id' => $team->id,
        'status' => TicketStatus::ASSIGNED,
    ]);

    $ticket->assignments()->create([
        'team_id' => $team->id,
        'assigned_by_id' => $user->id,
        'assigned_at' => now(),
    ]);

    $endorsement = TicketEndorsement::forceCreate([
        'ticket_id' => $ticket->id,
        'created_by_id' => $user->id,
        'reason' => 'Wrong department',
        'pre_endorsement_status' => TicketStatus::ASSIGNED->value,
        'status' => EndorsementStatus::PENDING->value,
    ]);
    $ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT]);

    // Team remains active (not deleted)
    $endorsementService = app(TicketEndorsementService::class);
    $result = $endorsementService->verifyEndorsement([
        'status' => EndorsementStatus::REJECTED->value,
        'rejection_reason' => 'Handle internally',
    ], $endorsement);

    expect($result['success'])->toBeTrue();

    // Ticket safely restores ASSIGNED and keeps its team
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::ASSIGNED);
    expect($ticket->team_id)->toBe($team->id);
});
