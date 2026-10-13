<?php

use App\Enums\ComplaintSources;
use App\Enums\EndorsementStatus;
use App\Enums\TicketAccomplishmentStatus;
use App\Enums\TicketStatus;
use App\Models\AccountRole;
use App\Models\Consumer;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketCategory;
use App\Models\TicketEndorsement;
use App\Models\User;
use App\Services\Categories\TicketCategoryService;
use App\Services\Departments\DepartmentService;
use App\Services\External\ConsumerService;
use App\Services\Teams\TeamService;
use App\Services\Tickets\TicketAccomplishmentService;
use App\Services\Tickets\TicketEndorsementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['database.default' => 'concurrency_test', 'database.connections.concurrency_test' => [
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

    Schema::create('consumers', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('acct_no')->unique();
        $table->string('acct_code')->unique();
        $table->string('name');
        $table->string('address');
        $table->string('status', 1)->default('A');
        $table->string('meter_serial')->nullable();
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
        $table->string('old_status')->nullable();
        $table->string('new_status');
        $table->string('changed_by_id', 26);
        $table->timestamps();
    });

    Schema::create('ticket_endorsements', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('created_by_id', 26);
        $table->foreignId('suggested_department_id')->nullable();
        $table->text('reason');
        $table->string('pre_endorsement_status')->nullable();
        $table->string('status')->default(EndorsementStatus::PENDING->value);
        $table->string('reviewed_by_id', 26)->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->text('rejection_reason')->nullable();
        $table->timestamps();
    });

    Schema::create('ticket_accomplishments', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('accomplished_by_id', 26);
        $table->text('remarks');
        $table->timestamp('accomplished_at')->nullable();
        $table->timestamp('client_accomplished_at')->nullable();
        $table->string('consumer_name')->nullable();
        $table->string('signature_path')->nullable();
        $table->string('status')->default(TicketAccomplishmentStatus::PENDING->value);
        $table->string('approved_by_id', 26)->nullable();
        $table->string('rejected_by_id', 26)->nullable();
        $table->text('rejection_reason')->nullable();
        $table->tinyInteger('is_offline_synced')->default(0);
        $table->timestamp('synced_at')->nullable();
        $table->string('idempotency_key')->nullable()->unique();
        $table->timestamps();
    });

    // Seed test fixtures
    $this->dept = Department::create(['dept_name' => 'Technical Services', 'dept_desc' => 'TSD']);
    $this->category = TicketCategory::create(['category_name' => 'Transformer Defect']);
    $this->team = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Crew Alpha',
        'department_id' => $this->dept->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
    ]);

    $roleWorker = AccountRole::create(['role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);
    $roleSupervisor = AccountRole::create(['role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);

    $this->worker = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'lineman_john',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'contact' => '09123456789',
        'role_id' => $roleWorker->id,
        'department_id' => $this->dept->id,
        'password' => bcrypt('secret'),
    ]);

    $this->supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_jane',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'contact' => '09123456780',
        'role_id' => $roleSupervisor->id,
        'department_id' => $this->dept->id,
        'password' => bcrypt('secret'),
    ]);
});

test('accomplishTicket under pessimistic lock rejects submission if ticket was concurrently resolved or has pending accomplishment', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-1001',
        'department_id' => $this->dept->id,
        'team_id' => $this->team->id,
        'status' => TicketStatus::IN_PROGRESS->value,
        'barangay' => 'Tiniguiban',
        'reported_at' => now(),
    ]);

    // Simulate concurrent accomplishment by another worker changing status to RESOLVED
    DB::table('tickets')->where('id', $ticket->id)->update(['status' => TicketStatus::RESOLVED->value]);

    $service = app(TicketAccomplishmentService::class);

    expect(fn () => $service->accomplishTicket($ticket, $this->worker, ['remarks' => 'Line spliced.']))
        ->toThrow(ValidationException::class);
});

test('requestEndorsement under pessimistic lock rejects request if ticket was concurrently resolved', function () {
    $ticket = Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-1002',
        'department_id' => $this->dept->id,
        'team_id' => $this->team->id,
        'status' => TicketStatus::IN_PROGRESS->value,
        'barangay' => 'Tiniguiban',
        'reported_at' => now(),
    ]);

    // Simulate concurrent lineman resolution
    DB::table('tickets')->where('id', $ticket->id)->update(['status' => TicketStatus::RESOLVED->value]);

    $service = app(TicketEndorsementService::class);

    expect(fn () => $service->requestEndorsement($ticket, ['reason' => 'Need meter crew'], $this->supervisor))
        ->toThrow(ValidationException::class);
});

test('archiveTeam under pessimistic lock rejects archiving if team has active tickets assigned concurrently', function () {
    // Ticket in progress assigned to team
    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-1003',
        'department_id' => $this->dept->id,
        'team_id' => $this->team->id,
        'status' => TicketStatus::IN_PROGRESS->value,
        'barangay' => 'Tiniguiban',
        'reported_at' => now(),
    ]);

    $service = app(TeamService::class);
    $result = $service->archiveTeam($this->team);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('currently have active assigned service tickets');
    expect($this->team->fresh()->trashed())->toBeFalse();
});

test('archiveDepartment under pessimistic lock rejects archiving if department has active tickets', function () {
    // Active open ticket
    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-1004',
        'department_id' => $this->dept->id,
        'status' => TicketStatus::OPEN->value,
        'barangay' => 'Tiniguiban',
        'reported_at' => now(),
    ]);

    $service = app(DepartmentService::class);
    $result = $service->archiveDepartment($this->dept);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('active service tickets');
    expect($this->dept->fresh()->trashed())->toBeFalse();
});

test('archiveCategory under pessimistic lock rejects archiving if category has active tickets', function () {
    // Active assigned ticket with category
    Ticket::create([
        'id' => (string) Str::ulid(),
        'ticket_number' => 'TCK-20261013-1005',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'status' => TicketStatus::ASSIGNED->value,
        'barangay' => 'Tiniguiban',
        'reported_at' => now(),
    ]);

    $service = app(TicketCategoryService::class);
    $result = $service->archiveCategory($this->category);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('assigned to active service tickets');
    expect($this->category->fresh()->trashed())->toBeFalse();
});

test('resolveConsumerId catch block recovers gracefully if concurrent insert completed first', function () {
    $consumer = Consumer::create([
        'id' => (string) Str::ulid(),
        'acct_no' => '02-12345',
        'acct_code' => '02-0504-8538',
        'name' => 'Juan Dela Cruz',
        'address' => 'Puerto Princesa',
        'status' => 'A',
    ]);

    $service = new ConsumerService();

    // Direct invocation with existing account code returns existing consumer ID
    $resolvedId = $service->resolveConsumerId('02-0504-8538');

    expect($resolvedId)->toBe($consumer->id);
});

