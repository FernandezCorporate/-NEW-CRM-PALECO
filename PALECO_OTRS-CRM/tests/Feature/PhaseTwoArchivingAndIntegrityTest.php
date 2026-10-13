<?php

use App\Enums\TicketStatus;
use App\Http\Resources\Api\TicketResource;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\Categories\TicketCategoryService;
use App\Services\Departments\DepartmentService;
use App\Services\Teams\TeamService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['database.default' => 'phase2_test', 'database.connections.phase2_test' => [
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
});

test('archiveDepartment: allows archiving if all associated tickets are closed, but blocks on open tickets', function () {
    $dept = Department::forceCreate(['id' => 1, 'dept_name' => 'Legacy Dept']);
    $service = app(DepartmentService::class);

    // Create a closed ticket
    $ticket = Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-101',
        'barangay' => 'San Pedro',
        'department_id' => $dept->id,
        'status' => TicketStatus::CLOSED,
    ]);

    // Archiving should succeed because all tickets are closed
    $result = $service->archiveDepartment($dept);
    expect($result['success'])->toBeTrue();
    expect($dept->fresh()->trashed())->toBeTrue();

    // Restore department
    $dept->restore();

    // Now add an active open ticket
    Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-102',
        'barangay' => 'San Pedro',
        'department_id' => $dept->id,
        'status' => TicketStatus::OPEN,
    ]);

    // Archiving must now be blocked
    $result2 = $service->archiveDepartment($dept);
    expect($result2['success'])->toBeFalse();
    expect($result2['message'])->toContain('active service tickets');
});

test('archiveTeam: allows archiving if tickets are closed or in endorsement, but blocks on assigned/in-progress', function () {
    $dept = Department::forceCreate(['id' => 1, 'dept_name' => 'Operations']);
    $team = Team::forceCreate([
        'id' => (string) Str::ulid(),
        'team_name' => 'Crew Delta',
        'department_id' => $dept->id,
    ]);
    $service = app(TeamService::class);

    // Create a closed ticket under this team
    Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-201',
        'barangay' => 'San Pedro',
        'department_id' => $dept->id,
        'team_id' => $team->id,
        'status' => TicketStatus::CLOSED,
    ]);

    // Create a pending endorsement ticket (responsibility transferred)
    Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-202',
        'barangay' => 'San Pedro',
        'department_id' => $dept->id,
        'team_id' => $team->id,
        'status' => TicketStatus::PENDING_ENDORSEMENT,
    ]);

    // Team should be archivable!
    $result = $service->archiveTeam($team);
    expect($result['success'])->toBeTrue();
    expect($team->fresh()->trashed())->toBeTrue();

    // Restore team
    $team->restore();

    // Create an actively assigned ticket
    Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-203',
        'barangay' => 'San Pedro',
        'department_id' => $dept->id,
        'team_id' => $team->id,
        'status' => TicketStatus::ASSIGNED,
    ]);

    // Team archiving must be blocked
    $result2 = $service->archiveTeam($team);
    expect($result2['success'])->toBeFalse();
    expect($result2['message'])->toContain('active assigned service tickets');
});

test('archiveCategory: allows archiving if all tickets are resolved/closed, but blocks on open tickets', function () {
    $category = TicketCategory::forceCreate([
        'id' => 1,
        'category_name' => 'Old Meter Fault',
    ]);
    $service = app(TicketCategoryService::class);

    Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-301',
        'barangay' => 'San Pedro',
        'category_id' => $category->id,
        'status' => TicketStatus::RESOLVED,
    ]);

    // Category should be archivable
    $result = $service->archiveCategory($category);
    expect($result['success'])->toBeTrue();
    expect($category->fresh()->trashed())->toBeTrue();

    $category->restore();

    // Add in-progress ticket
    Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-302',
        'barangay' => 'San Pedro',
        'category_id' => $category->id,
        'status' => TicketStatus::IN_PROGRESS,
    ]);

    $result2 = $service->archiveCategory($category);
    expect($result2['success'])->toBeFalse();
    expect($result2['message'])->toContain('active service tickets');
});

test('force delete remains strictly blocked when any historical tickets exist', function () {
    $category = TicketCategory::forceCreate([
        'id' => 10,
        'category_name' => 'Historical Category',
    ]);
    Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-401',
        'barangay' => 'San Pedro',
        'category_id' => $category->id,
        'status' => TicketStatus::CLOSED,
    ]);
    $category->delete(); // trashed

    $service = app(TicketCategoryService::class);
    $result = $service->permanentlyDeleteCategory($category->id);
    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('referenced by existing service tickets');
});

test('Ticket model and TicketResource gracefully preserve and display archived relations with withTrashed', function () {
    $dept = Department::forceCreate(['id' => 1, 'dept_name' => 'Consumer Care']);
    $team = Team::forceCreate([
        'id' => (string) Str::ulid(),
        'team_name' => 'Crew Echo',
        'department_id' => $dept->id,
    ]);
    $category = TicketCategory::forceCreate([
        'id' => 1,
        'category_name' => 'Billing Inquiry',
    ]);

    $ticket = Ticket::forceCreate([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261013-501',
        'barangay' => 'San Pedro',
        'department_id' => $dept->id,
        'team_id' => $team->id,
        'category_id' => $category->id,
        'status' => TicketStatus::CLOSED,
    ]);

    $ticket->assignments()->create([
        'team_id' => $team->id,
        'assigned_by_id' => (string) Str::ulid(),
        'assigned_at' => now(),
    ]);

    // Archive category and team
    $category->delete();
    $team->delete();

    // Verify Eloquent relationships still load without null
    $ticket->refresh();
    expect($ticket->category)->not->toBeNull();
    expect($ticket->category->category_name)->toBe('Billing Inquiry');
    expect($ticket->category->trashed())->toBeTrue();

    expect($ticket->team)->not->toBeNull();
    expect($ticket->team->team_name)->toBe('Crew Echo');
    expect($ticket->team->trashed())->toBeTrue();

    // Verify assignment timeline relationship also loads trashed team
    expect($ticket->assignments->first()->team)->not->toBeNull();
    expect($ticket->assignments->first()->team->team_name)->toBe('Crew Echo');

    // Verify TicketResource formats with (Archived) suffix
    $resourceData = (new TicketResource($ticket))->toArray(new Request());
    expect($resourceData['category_name'])->toBe('Billing Inquiry (Archived)');
    expect($resourceData['team_name'])->toBe('Crew Echo (Archived)');
});
