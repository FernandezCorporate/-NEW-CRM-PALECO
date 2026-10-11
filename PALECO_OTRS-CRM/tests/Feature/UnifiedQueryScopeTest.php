<?php

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['database.default' => 'scope_test', 'database.connections.scope_test' => [
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

    Schema::create('departments', function (Blueprint $table) {
        $table->id();
        $table->string('dept_name');
        $table->string('dept_desc')->nullable();
        $table->softDeletes();
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
});

test('Ticket unified search matches multi-field criteria including category name and address', function () {
    $category = TicketCategory::create([
        'id' => 1,
        'category_name' => 'Power Interruption / Blackout',
    ]);

    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-2026-001',
        'barangay' => 'San Pedro',
        'purok' => 'Purok Masagana',
        'street' => 'Malvar Street',
        'category_id' => $category->id,
        'status' => TicketStatus::OPEN->value,
        'complaint_description' => 'Complete area outage',
        'complaint_source' => 'phone_call',
    ]);

    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-2026-002',
        'barangay' => 'Poblacion',
        'purok' => 'Purok Pag-asa',
        'street' => 'Rizal Avenue',
        'other_category' => 1,
        'other_category_name' => 'Transformer Sparking',
        'status' => TicketStatus::ASSIGNED->value,
        'complaint_description' => 'Sparking equipment near pole',
        'complaint_source' => 'walk_in',
    ]);

    // Search by ticket number
    expect(Ticket::search('TKT-2026-001')->count())->toBe(1);

    // Search by category relation name
    expect(Ticket::search('Blackout')->count())->toBe(1);

    // Search by other_category_name
    expect(Ticket::search('Transformer')->count())->toBe(1);

    // Search by barangay
    expect(Ticket::search('Poblacion')->count())->toBe(1);

    // Search by purok
    expect(Ticket::search('Masagana')->count())->toBe(1);

    // Search by street
    expect(Ticket::search('Malvar')->count())->toBe(1);

    // Search by complaint description
    expect(Ticket::search('Sparking')->count())->toBe(1);

    // Search by complaint source
    expect(Ticket::search('phone_call')->count())->toBe(1);

    // Empty search returns all
    expect(Ticket::search('')->count())->toBe(2);
    expect(Ticket::search(null)->count())->toBe(2);

    // Deprecated apiSearch alias returns identical result
    expect(Ticket::apiSearch('Blackout')->count())->toBe(1);
});

test('Ticket unified filterByCategory is polymorphic across integer IDs, numeric strings, category names, and other', function () {
    $cat1 = TicketCategory::create(['id' => 10, 'category_name' => 'High Voltage Fluctuation']);
    $cat2 = TicketCategory::create(['id' => 20, 'category_name' => 'Billing Inquiry']);

    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-CAT-001',
        'barangay' => 'San Jose',
        'category_id' => $cat1->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-CAT-002',
        'barangay' => 'San Jose',
        'category_id' => $cat2->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-CAT-003',
        'barangay' => 'San Jose',
        'other_category' => 1,
        'other_category_name' => 'Solar Rooftop Sync',
        'status' => TicketStatus::OPEN->value,
    ]);

    // 1. Filter by integer ID (Web Livewire dropdown)
    expect(Ticket::filterByCategory($cat1->id)->count())->toBe(1)
        ->and(Ticket::filterByCategory($cat1->id)->first()->ticket_number)->toBe('TKT-CAT-001');

    // 2. Filter by numeric string ID (Web URL query parameter)
    expect(Ticket::filterByCategory((string) $cat2->id)->count())->toBe(1)
        ->and(Ticket::filterByCategory((string) $cat2->id)->first()->ticket_number)->toBe('TKT-CAT-002');

    // 3. Filter by category name string (Mobile API param)
    expect(Ticket::filterByCategory('High Voltage Fluctuation')->count())->toBe(1)
        ->and(Ticket::filterByCategory('High Voltage Fluctuation')->first()->ticket_number)->toBe('TKT-CAT-001');

    // 4. Filter by 'other'
    expect(Ticket::filterByCategory('other')->count())->toBe(1)
        ->and(Ticket::filterByCategory('other')->first()->ticket_number)->toBe('TKT-CAT-003');

    // 5. 'all' or empty returns all tickets without filtering
    expect(Ticket::filterByCategory('all')->count())->toBe(3);
    expect(Ticket::filterByCategory(null)->count())->toBe(3);

    // 6. Deprecated apiFilterByCategoryName alias forwards cleanly
    expect(Ticket::apiFilterByCategoryName('Billing Inquiry')->count())->toBe(1);
    expect(Ticket::apiFilterByCategoryName('other')->count())->toBe(1);
});

test('Ticket unified filterByStatus supports both string values and TicketStatus enums, ignoring all', function () {
    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-STAT-001',
        'barangay' => 'Bgy 1',
        'status' => TicketStatus::OPEN->value,
    ]);

    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-STAT-002',
        'barangay' => 'Bgy 2',
        'status' => TicketStatus::ASSIGNED->value,
    ]);

    // String status
    expect(Ticket::filterByStatus('open')->count())->toBe(1)
        ->and(Ticket::filterByStatus('open')->first()->ticket_number)->toBe('TKT-STAT-001');

    // Enum status
    expect(Ticket::filterByStatus(TicketStatus::ASSIGNED)->count())->toBe(1)
        ->and(Ticket::filterByStatus(TicketStatus::ASSIGNED)->first()->ticket_number)->toBe('TKT-STAT-002');

    // 'all' ignores filter
    expect(Ticket::filterByStatus('all')->count())->toBe(2);
    expect(Ticket::filterByStatus(null)->count())->toBe(2);

    // Invalid status string gracefully ignored
    expect(Ticket::filterByStatus('non_existent_status')->count())->toBe(2);

    // Deprecated apiFilterByStatus alias
    expect(Ticket::apiFilterByStatus('assigned')->count())->toBe(1);
});

test('Ticket unified sort supports ticket numbers, status, and dates', function () {
    Carbon::setTestNow('2026-10-10 09:00:00');
    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-B',
        'barangay' => 'Bgy 1',
        'status' => TicketStatus::RESOLVED->value,
    ]);

    Carbon::setTestNow('2026-10-10 10:00:00');
    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TKT-A',
        'barangay' => 'Bgy 2',
        'status' => TicketStatus::OPEN->value,
    ]);

    // ticket_number_asc
    $asc = Ticket::sort('ticket_number_asc')->pluck('ticket_number')->toArray();
    expect($asc)->toBe(['TKT-A', 'TKT-B']);

    // ticket_number_desc
    $desc = Ticket::sort('ticket_number_desc')->pluck('ticket_number')->toArray();
    expect($desc)->toBe(['TKT-B', 'TKT-A']);

    // oldest
    $oldest = Ticket::sort('oldest')->pluck('ticket_number')->toArray();
    expect($oldest)->toBe(['TKT-B', 'TKT-A']);

    // default (latest)
    $latest = Ticket::sort('newest')->pluck('ticket_number')->toArray();
    expect($latest)->toBe(['TKT-A', 'TKT-B']);

    // Deprecated apiSort alias
    $apiAsc = Ticket::apiSort('ticket_number_asc')->pluck('ticket_number')->toArray();
    expect($apiAsc)->toBe(['TKT-A', 'TKT-B']);
});

test('Team unified scopes handle search, status lifecycle filtering, and sorting', function () {
    $dept = Department::create(['dept_name' => 'Technical Operations']);

    $activeTeam = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Alpha Emergency Response Crew',
        'team_desc' => 'Rapid response line squad',
        'department_id' => $dept->id,
        'shift_start' => '08:00:00',
        'shift_end' => '17:00:00',
    ]);

    $archivedTeam = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Bravo Standby Crew',
        'team_desc' => 'Backup team',
        'department_id' => $dept->id,
    ]);
    $archivedTeam->delete(); // Soft delete

    // Search across words
    expect(Team::withTrashed()->search('Alpha Emergency')->count())->toBe(1);
    expect(Team::withTrashed()->search('Response')->count())->toBe(1);

    // filterByStatus
    expect(Team::withTrashed()->filterByStatus('active')->count())->toBe(1)
        ->and(Team::withTrashed()->filterByStatus('active')->first()->team_name)->toBe('Alpha Emergency Response Crew');

    expect(Team::withTrashed()->filterByStatus('archived')->count())->toBe(1)
        ->and(Team::withTrashed()->filterByStatus('archived')->first()->team_name)->toBe('Bravo Standby Crew');

    expect(Team::withTrashed()->filterByStatus('all')->count())->toBe(2);

    // Sorting
    $sorted = Team::withTrashed()->sort('team_nameASC')->pluck('team_name')->toArray();
    expect($sorted)->toBe(['Alpha Emergency Response Crew', 'Bravo Standby Crew']);

    // Deprecated aliases
    expect(Team::withTrashed()->apiSearch('Alpha')->count())->toBe(1);
    expect(Team::withTrashed()->apiFilterStatus('archived')->count())->toBe(1);
    expect(Team::withTrashed()->apiSort('team_nameASC')->count())->toBe(2);
});
