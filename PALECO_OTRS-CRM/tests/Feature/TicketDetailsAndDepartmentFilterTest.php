<?php

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketRemark;
use App\Models\User;
use App\Services\Tickets\TicketService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['database.default' => 'dept_filter_test', 'database.connections.dept_filter_test' => [
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
        $table->boolean('is_active')->default(true);
        $table->string('password');
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('ticket_categories', function (Blueprint $table) {
        $table->id();
        $table->string('category_name');
        $table->text('category_desc')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('teams', function (Blueprint $table) {
        $table->id();
        $table->string('team_name');
        $table->foreignId('department_id')->constrained();
        $table->time('shift_start')->nullable();
        $table->time('shift_end')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('tickets', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_number')->unique();
        $table->string('parent_ticket_id', 26)->nullable();
        $table->string('complaint_source');
        $table->foreignId('category_id')->nullable();
        $table->boolean('other_category')->default(false);
        $table->string('other_category_name')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->string('team_id', 26)->nullable();
        $table->string('status');
        $table->text('complaint_description');
        $table->string('purok')->nullable();
        $table->string('street')->nullable();
        $table->string('barangay');
        $table->string('landmark')->nullable();
        $table->string('consumer_contact')->nullable();
        $table->string('consumer_id', 26)->nullable();
        $table->string('created_by_id', 26)->nullable();
        $table->timestamp('reported_at')->useCurrent();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('ticket_status_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ticket_id');
        $table->string('old_status')->nullable();
        $table->string('new_status');
        $table->string('changed_by_id', 26)->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_assignments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ticket_id');
        $table->foreignId('team_id');
        $table->string('assigned_by', 26);
        $table->text('reason')->nullable();
        $table->timestamp('unassigned_at')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_endorsements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ticket_id');
        $table->foreignId('suggested_dept_id');
        $table->string('created_by_id', 26)->nullable();
        $table->string('reviewed_by', 26)->nullable();
        $table->string('status')->default('pending');
        $table->text('endorsement_reason')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_accomplishments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ticket_id');
        $table->string('accomplished_by', 26);
        $table->string('approved_by', 26)->nullable();
        $table->string('rejected_by', 26)->nullable();
        $table->string('status')->default('pending');
        $table->timestamp('accomplished_at')->useCurrent();
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_remarks', function (Blueprint $table) {
        $table->ulid('id')->primary();
        $table->foreignId('ticket_id');
        $table->string('user_id', 26);
        $table->text('body');
        $table->boolean('is_internal')->default(false);
        $table->timestamps();
    });

    $role = AccountRole::create(['role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);

    $this->user = User::create([
        'id' => '01JUSERCWD00000000000000001',
        'username' => 'cwdofficer',
        'first_name' => 'CWD',
        'last_name' => 'Staff',
        'contact' => '09123456789',
        'role_id' => $role->id,
        'is_active' => true,
        'password' => bcrypt('password'),
    ]);

    $this->deptTech = Department::create(['dept_name' => 'Technical Services']);
    $this->deptMeter = Department::create(['dept_name' => 'Metering Department']);

    $this->catPower = TicketCategory::create(['category_name' => 'Power Interruption']);
});

test('Ticket model scopeFilterByDepartment filters accurately by ID, name, unassigned, and all', function () {
    $t1 = Ticket::create([
        'ticket_number' => 'TKT-2026-0001',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'department_id' => $this->deptTech->id,
        'status' => TicketStatus::OPEN->value,
        'complaint_description' => 'Feeder tripped',
        'barangay' => 'San Pedro',
        'created_by_id' => $this->user->id,
    ]);

    $t2 = Ticket::create([
        'ticket_number' => 'TKT-2026-0002',
        'complaint_source' => ComplaintSources::WALK_IN->value,
        'department_id' => $this->deptMeter->id,
        'status' => TicketStatus::OPEN->value,
        'complaint_description' => 'Broken glass meter',
        'barangay' => 'Tiniguiban',
        'created_by_id' => $this->user->id,
    ]);

    $t3 = Ticket::create([
        'ticket_number' => 'TKT-2026-0003',
        'complaint_source' => ComplaintSources::EMAIL->value,
        'department_id' => null,
        'status' => TicketStatus::OPEN->value,
        'complaint_description' => 'General inquiry unassigned',
        'barangay' => 'Sta. Monica',
        'created_by_id' => $this->user->id,
    ]);

    // Filter by numeric ID
    $techResults = Ticket::filterByDepartment($this->deptTech->id)->get();
    expect($techResults)->toHaveCount(1)
        ->and($techResults->first()->ticket_number)->toBe('TKT-2026-0001');

    // Filter by string department name
    $meterResults = Ticket::filterByDepartment('Metering Department')->get();
    expect($meterResults)->toHaveCount(1)
        ->and($meterResults->first()->ticket_number)->toBe('TKT-2026-0002');

    // Filter by 'unassigned'
    $unassignedResults = Ticket::filterByDepartment('unassigned')->get();
    expect($unassignedResults)->toHaveCount(1)
        ->and($unassignedResults->first()->ticket_number)->toBe('TKT-2026-0003');

    // Filter by 'all' or empty string returns all tickets
    $allResults = Ticket::filterByDepartment('all')->get();
    expect($allResults)->toHaveCount(3);

    $emptyResults = Ticket::filterByDepartment('')->get();
    expect($emptyResults)->toHaveCount(3);
});

test('TicketService getTicketList applies department filtering and supplies server-passed departments', function () {
    Ticket::create([
        'ticket_number' => 'TKT-2026-0010',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'department_id' => $this->deptTech->id,
        'status' => TicketStatus::OPEN->value,
        'complaint_description' => 'Transformer spark',
        'barangay' => 'San Pedro',
        'created_by_id' => $this->user->id,
    ]);

    Ticket::create([
        'ticket_number' => 'TKT-2026-0020',
        'complaint_source' => ComplaintSources::WALK_IN->value,
        'department_id' => $this->deptMeter->id,
        'status' => TicketStatus::OPEN->value,
        'complaint_description' => 'Meter inspection',
        'barangay' => 'San Jose',
        'created_by_id' => $this->user->id,
    ]);

    $service = app(TicketService::class);

    // Request with department filter
    $request = Request::create('/cwd/tickets', 'GET', ['department' => (string) $this->deptTech->id]);
    $result = $service->getTicketList($request);

    expect($result)->toHaveKeys(['tickets', 'categories', 'departments', 'statuses'])
        ->and($result['tickets']->total())->toBe(1)
        ->and($result['tickets']->first()->ticket_number)->toBe('TKT-2026-0010')
        ->and($result['departments'])->toHaveCount(2);
});

test('TicketService getTicketDetails eager loads remarks with author and role', function () {
    $ticket = Ticket::create([
        'ticket_number' => 'TKT-2026-0030',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'department_id' => $this->deptTech->id,
        'status' => TicketStatus::OPEN->value,
        'complaint_description' => 'Downed power wire',
        'barangay' => 'Poblacion',
        'created_by_id' => $this->user->id,
    ]);

    TicketRemark::create([
        'ticket_id' => $ticket->id,
        'user_id' => $this->user->id,
        'body' => 'Priority dispatch: wire poses pedestrian danger.',
        'is_internal' => true,
    ]);

    $service = app(TicketService::class);
    $result = $service->getTicketDetails($ticket);

    $loadedTicket = $result['ticket'];
    expect($loadedTicket->relationLoaded('remarks'))->toBeTrue()
        ->and($loadedTicket->remarks)->toHaveCount(1)
        ->and($loadedTicket->remarks->first()->relationLoaded('author'))->toBeTrue()
        ->and($loadedTicket->remarks->first()->author->relationLoaded('role'))->toBeTrue();
});

test('TicketStatus enum provides valid badgeClasses, colorHex, and chartClass', function () {
    foreach (TicketStatus::cases() as $status) {
        expect($status->badgeClasses())->toBeString()->not->toBeEmpty()
            ->and($status->colorHex())->toMatch('/^#[0-9a-fA-F]{6}$/')
            ->and($status->chartClass())->toStartWith('bg-');
    }
});

test('ticket details view renders prominent status badge, distinct child ticket action button, neutral unassigned team, and 5 dynamic tabs', function () {
    Gate::define('webView', fn ($user, $ticket) => true);
    Gate::define('createChild', fn ($user, $ticket) => true);

    $ticket = Ticket::create([
        'ticket_number' => 'TKT-2026-9999',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'department_id' => $this->deptTech->id,
        'team_id' => null,
        'status' => TicketStatus::IN_PROGRESS->value,
        'complaint_description' => 'Feeder outage test description',
        'barangay' => 'San Pedro',
        'created_by_id' => $this->user->id,
    ]);

    // Create a status change log entry
    \App\Models\TicketStatusLog::create([
        'ticket_id' => $ticket->id,
        'old_status' => TicketStatus::OPEN->value,
        'new_status' => TicketStatus::IN_PROGRESS->value,
        'changed_by_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('cwd.tickets.show', $ticket));
    $response->assertOk();

    // Prominent lifecycle status badge
    $response->assertSee('TKT-2026-9999');
    $response->assertSee(TicketStatus::IN_PROGRESS->label());
    $response->assertSee(TicketStatus::IN_PROGRESS->badgeClasses(), false);

    // Status transition pills in status changes table
    $response->assertSee(TicketStatus::OPEN->badgeClasses(), false);
    $response->assertSee(TicketStatus::OPEN->label());
    $response->assertSee(TicketStatus::IN_PROGRESS->badgeClasses(), false);

    // Distinct Child Ticket Action Button (not an informational pill)
    $response->assertSee('Create Child Ticket');
    $response->assertSee('bg-indigo-600');

    // Neutral unassigned team state with NO plus icon in the team box
    $response->assertSee('Team not yet assigned');
    $response->assertSee('Awaiting supervisor dispatch');

    // All 5 dynamic history tab targets
    $response->assertSee('data-tab-target="status-history"', false);
    $response->assertSee('data-tab-target="assignment-history"', false);
    $response->assertSee('data-tab-target="endorsement-history"', false);
    $response->assertSee('data-tab-target="accomplishment-history"', false);
    $response->assertSee('data-tab-target="remark-history"', false);

    // Furnished remarks component and form
    $response->assertSee('data-tab-panel="remark-history"', false);
    $response->assertSee('Add Operational Remark');
    $response->assertSee('Internal memo only');
    $response->assertSee('Post Remark');
});

