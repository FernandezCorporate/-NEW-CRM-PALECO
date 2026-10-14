<?php

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['database.default' => 'payload_test', 'database.connections.payload_test' => [
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

    Schema::create('consumers', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('acct_no')->unique();
        $table->string('acct_code')->nullable();
        $table->string('name');
        $table->string('address');
        $table->string('status');
        $table->string('meter_serial')->nullable();
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

    $this->supRole = AccountRole::create(['id' => 1, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);
    $this->cwdRole = AccountRole::create(['id' => 2, 'role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);
    $this->dept = Department::create(['id' => 10, 'dept_name' => 'Technical Services Department']);
    $this->category = TicketCategory::create(['id' => 5, 'category_name' => 'Distribution Line Fault']);

    $this->supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_tsd',
        'first_name' => 'Roberto',
        'last_name' => 'Cruz',
        'contact' => '09123456789',
        'password' => bcrypt('password'),
        'role_id' => $this->supRole->id,
        'department_id' => $this->dept->id,
    ]);

    $this->cwdOfficer = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'cwd_officer_1',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact' => '09123456780',
        'password' => bcrypt('password'),
        'role_id' => $this->cwdRole->id,
        'department_id' => null,
    ]);
});

test('TicketResource correctly exposes all newly added mobile fields in list response', function () {
    $parentTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261014-001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Main feeder snapped',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'purok' => 'Bayanihan',
        'street' => 'Rizal Ave',
        'barangay' => 'San Pedro',
        'landmark' => 'Near Church',
        'status' => TicketStatus::ASSIGNED->value,
        'created_by_id' => $this->supervisor->id,
        'reported_at' => now(),
    ]);

    $childTicket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-261014-001-C1',
        'parent_ticket_id' => $parentTicket->id,
        'complaint_source' => ComplaintSources::SMS->value,
        'consumer_contact' => '09987654321',
        'complaint_description' => 'Secondary line clearance for feeder',
        'department_id' => $this->dept->id,
        'other_category' => true,
        'other_category_name' => 'Emergency Vegetation Trimming',
        'purok' => 'Bayanihan',
        'street' => 'Rizal Ave',
        'barangay' => 'San Pedro',
        'status' => TicketStatus::CANCELLED->value,
        'cancellation_reason' => 'Duplicate field report',
        'cancelled_at' => now(),
        'is_offline_synced' => true,
        'synced_at' => now(),
        'client_started_at' => now()->subMinutes(10),
        'created_by_id' => $this->supervisor->id,
        'reported_at' => now(),
    ]);

    Sanctum::actingAs($this->supervisor);

    $response = $this->getJson('/api/tickets');

    $response->assertOk()
        ->assertJsonPath('success', true);

    $items = collect($response->json('data'));
    $childItem = $items->firstWhere('ticket_number', 'TKT-261014-001-C1');
    expect($childItem)->not->toBeNull();

    // Verify Department Silo
    expect($childItem['department_id'])->toBe($this->dept->id);
    expect($childItem['department_name'])->toBe('Technical Services Department');

    // Verify Category Details
    expect($childItem['is_other_category'])->toBeTrue();
    expect($childItem['category_name'])->toBe('Emergency Vegetation Trimming');
    expect($childItem['other_category_name'])->toBe('Emergency Vegetation Trimming');

    // Verify Hierarchy
    expect($childItem['is_child'])->toBeTrue();
    expect($childItem['parent_ticket_id'])->toBe($parentTicket->id);
    expect($childItem['parent_ticket_number'])->toBe('TKT-261014-001');

    // Verify Cancellation
    expect($childItem['cancellation_reason'])->toBe('Duplicate field report');
    expect($childItem['cancelled_at'])->not->toBeNull();

    // Verify Offline Sync Telemetry
    expect($childItem['is_offline_synced'])->toBeTrue();
    expect($childItem['synced_at'])->not->toBeNull();
    expect($childItem['client_started_at'])->not->toBeNull();
});

test('TicketDetailedResource correctly exposes all fields for force-closed ticket and formatted archived relations', function () {
    $archivedDept = Department::create(['id' => 20, 'dept_name' => 'Substation Maintenance']);
    $archivedDept->delete();

    $archivedCat = TicketCategory::create(['id' => 99, 'category_name' => 'Old Transformer Overload']);
    $archivedCat->delete();

    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-FORCE-CLOSED-99',
        'complaint_source' => ComplaintSources::WALK_IN->value,
        'consumer_contact' => '09121112222',
        'complaint_description' => 'Transformer sparks and high buzzing sound',
        'department_id' => $archivedDept->id,
        'category_id' => $archivedCat->id,
        'purok' => 'Purok 4',
        'street' => 'Quezon St',
        'barangay' => 'Tiniguiban',
        'landmark' => 'Behind Market',
        'status' => TicketStatus::CLOSED->value,
        'is_force_closed' => true,
        'closure_reason' => 'Service re-routed via secondary feeder.',
        'closed_by_id' => $this->cwdOfficer->id,
        'closed_at' => now(),
        'is_offline_synced' => true,
        'synced_at' => now(),
        'client_started_at' => now()->subHours(1),
        'created_by_id' => $this->supervisor->id,
        'reported_at' => now()->subHours(2),
    ]);

    // Update supervisor department to access the ticket
    $this->supervisor->update(['department_id' => $archivedDept->id]);
    Sanctum::actingAs($this->supervisor);

    $response = $this->getJson("/api/tickets/{$ticket->id}");

    $response->assertOk()
        ->assertJsonPath('success', true);

    $data = $response->json('data');

    // Verify Department Silo with (Archived) notation
    expect($data['department_id'])->toBe($archivedDept->id);
    expect($data['department_name'])->toBe('Substation Maintenance (Archived)');

    // Verify Hierarchy (root ticket)
    expect($data['is_child'])->toBeFalse();
    expect($data['parent_ticket_id'])->toBeNull();
    expect($data['parent_ticket_number'])->toBeNull();

    // Verify Force Closure details
    expect($data['is_force_closed'])->toBeTrue();
    expect($data['closure_reason'])->toBe('Service re-routed via secondary feeder.');
    expect($data['closed_by'])->toBeArray();
    expect($data['closed_by']['id'])->toBe($this->cwdOfficer->id);
    expect($data['closed_by']['name'])->toBe('Maria Santos');
    expect($data['closed_by']['role'])->toBe('CWD Officer');

    // Verify Offline Sync Telemetry
    expect($data['is_offline_synced'])->toBeTrue();
    expect($data['synced_at'])->not->toBeNull();
    expect($data['client_started_at'])->not->toBeNull();

    // Verify Complaint details
    expect($data['complaint']['category_id'])->toBe($archivedCat->id);
    expect($data['complaint']['category_name'])->toBe('Old Transformer Overload (Archived)');
    expect($data['complaint']['is_other_category'])->toBeFalse();
    expect($data['complaint']['purok'])->toBe('Purok 4');
    expect($data['complaint']['street'])->toBe('Quezon St');
    expect($data['complaint']['barangay'])->toBe('Tiniguiban');
    expect($data['complaint']['landmark'])->toBe('Behind Market');
});

test('TicketDetailedResource correctly exposes cancellation metadata and child ticket collection for parent ticket', function () {
    $parent = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-PARENT-100',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Feeder tripped, multiple areas affected',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'San Pedro',
        'status' => TicketStatus::OPEN->value,
        'created_by_id' => $this->supervisor->id,
        'reported_at' => now(),
    ]);

    $child = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-PARENT-100-C1',
        'parent_ticket_id' => $parent->id,
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'consumer_contact' => '09123456789',
        'complaint_description' => 'Branch line replacement',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'San Pedro',
        'status' => TicketStatus::CANCELLED->value,
        'cancellation_reason' => 'Customer confirmed power already restored by auto-recloser',
        'cancelled_at' => now(),
        'created_by_id' => $this->supervisor->id,
        'reported_at' => now(),
    ]);

    Sanctum::actingAs($this->supervisor);

    // 1. Inspect parent ticket: should list child tickets and have is_child false
    $parentResp = $this->getJson("/api/tickets/{$parent->id}");
    $parentResp->assertOk();
    $parentData = $parentResp->json('data');

    expect($parentData['is_child'])->toBeFalse();
    expect($parentData['parent_ticket_id'])->toBeNull();
    expect($parentData['parent_ticket_number'])->toBeNull();
    expect($parentData['child_tickets_count'])->toBe(1);
    expect($parentData['child_tickets'])->toHaveCount(1);
    expect($parentData['child_tickets'][0]['ticket_number'])->toBe('TKT-PARENT-100-C1');
    expect($parentData['is_force_closed'])->toBeFalse();
    expect($parentData['closure_reason'])->toBeNull();
    expect($parentData['closed_by'])->toBeNull();

    // 2. Inspect child ticket: should have is_child true, parent info, and cancellation details
    $childResp = $this->getJson("/api/tickets/{$child->id}");
    $childResp->assertOk();
    $childData = $childResp->json('data');

    expect($childData['is_child'])->toBeTrue();
    expect($childData['parent_ticket_id'])->toBe($parent->id);
    expect($childData['parent_ticket_number'])->toBe('TKT-PARENT-100');
    expect($childData['status'])->toBe(TicketStatus::CANCELLED->value);
    expect($childData['cancellation_reason'])->toBe('Customer confirmed power already restored by auto-recloser');
    expect($childData['cancelled_at'])->not->toBeNull();
});
