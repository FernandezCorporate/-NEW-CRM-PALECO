<?php

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Http\Requests\Teams\StoreTeamRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Http\Requests\Tickets\AssignTicketRequest;
use App\Http\Requests\Tickets\StoreChildTicketRequest;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\SubmitAccomplishmentReportRequest;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Policies\TicketPolicy;
use App\Services\Teams\TeamService;
use App\Services\Tickets\TicketAccomplishmentService;
use App\Services\Tickets\TicketService;
use App\Services\Users\UserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['database.default' => 'constraints_test', 'database.connections.constraints_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);

    Schema::create('activity_log', function (Blueprint $table) {
        $table->id();
        $table->string('log_name')->nullable();
        $table->text('description')->nullable();
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

    Schema::create('team_roles', function (Blueprint $table) {
        $table->id();
        $table->string('role_name');
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
        $table->string('password')->default('hashed');
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('personal_access_tokens', function (Blueprint $table) {
        $table->id();
        $table->morphs('tokenable');
        $table->string('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });

    Schema::create('teams', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('team_name');
        $table->string('team_desc')->nullable();
        $table->time('shift_start');
        $table->time('shift_end');
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
        $table->text('category_desc')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('tickets', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_number')->unique();
        $table->string('parent_ticket_id', 26)->nullable();
        $table->string('team_id', 26)->nullable();
        $table->foreignId('department_id')->nullable();
        $table->foreignId('category_id')->nullable();
        $table->string('created_by_id', 26)->nullable();
        $table->string('consumer_id')->nullable();
        $table->string('consumer_contact')->nullable();
        $table->string('complaint_source')->default('walk_in');
        $table->text('complaint_description');
        $table->boolean('other_category')->default(false);
        $table->string('other_category_name')->nullable();
        $table->string('status')->default('open');
        $table->timestamp('reported_at')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('client_started_at')->nullable();
        $table->boolean('is_offline_synced')->default(false);
        $table->timestamp('synced_at')->nullable();
        $table->string('purok')->nullable();
        $table->string('street')->nullable();
        $table->string('barangay')->nullable();
        $table->string('landmark')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('ticket_assignments', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('team_id', 26);
        $table->string('assigned_by_id', 26);
        $table->text('reason')->nullable();
        $table->timestamp('assigned_at')->useCurrent();
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

    Schema::create('ticket_accomplishments', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('accomplished_by_id', 26);
        $table->string('status')->default('pending');
        $table->text('remarks')->nullable();
        $table->string('idempotency_key')->nullable();
        $table->timestamps();
    });

    // Seed roles
    AccountRole::create(['id' => 1, 'role_name' => 'Admin', 'slug_identifier' => 'admin']);
    AccountRole::create(['id' => 2, 'role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);
    AccountRole::create(['id' => 3, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);
    AccountRole::create(['id' => 4, 'role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);

    TeamRole::create(['id' => 1, 'role_name' => 'Lead Lineman']);
    TeamRole::create(['id' => 2, 'role_name' => 'Assistant Lineman']);
});

test('Constraint 1 & 2: TicketService rejects cross-department and soft-deleted team assignment', function () {
    $deptA = Department::create(['id' => 1, 'dept_name' => 'Technical Services']);
    $deptB = Department::create(['id' => 2, 'dept_name' => 'Consumer Services']);

    $supervisor = User::create([
        'id' => '01JUSER0000000000000000001',
        'username' => 'sup1',
        'first_name' => 'Super',
        'last_name' => 'Visor',
        'contact' => '09123456789',
        'role_id' => 3,
        'department_id' => 1,
    ]);

    $teamOtherDept = Team::create([
        'id' => '01JTEAM0000000000000000001',
        'team_name' => 'Team Consumer',
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'department_id' => 2,
    ]);

    $teamSoftDeleted = Team::create([
        'id' => '01JTEAM0000000000000000002',
        'team_name' => 'Team Deleted',
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'department_id' => 1,
    ]);
    $teamSoftDeleted->delete(); // Soft delete

    $ticket = Ticket::create([
        'ticket_number' => 'TKT-261013-001',
        'department_id' => 1,
        'complaint_description' => 'Power transformer humming',
        'status' => TicketStatus::OPEN,
    ]);

    $ticketService = app(TicketService::class);

    // Cross-department assignment rejection
    expect(fn () => $ticketService->assignTicket($ticket, $teamOtherDept->id, $supervisor))
        ->toThrow(ValidationException::class);

    // Soft-deleted team assignment rejection
    expect(fn () => $ticketService->assignTicket($ticket, $teamSoftDeleted->id, $supervisor))
        ->toThrow(ValidationException::class);
});

test('Constraint 4: Single-level child nesting guard prevents subordinate child tickets from spawning children', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Services']);
    $officer = User::create([
        'id' => '01JUSER0000000000000000002',
        'username' => 'cwd1',
        'first_name' => 'CWD',
        'last_name' => 'Officer',
        'contact' => '09123456789',
        'role_id' => 2,
        'department_id' => 1,
    ]);

    $rootTicket = Ticket::create([
        'ticket_number' => 'TKT-261013-010',
        'parent_ticket_id' => null,
        'department_id' => 1,
        'complaint_description' => 'Main line down',
        'status' => TicketStatus::OPEN,
    ]);

    $childTicket = Ticket::create([
        'ticket_number' => 'TKT-261013-010-1',
        'parent_ticket_id' => $rootTicket->id,
        'department_id' => 1,
        'complaint_description' => 'Trim trees around line',
        'status' => TicketStatus::OPEN,
    ]);

    $ticketService = app(TicketService::class);

    // Service-level assertion
    expect(fn () => $ticketService->createManualChildTicket($childTicket, [
        'department_id' => 1,
        'complaint_description' => 'Illegal grandchild sub-task',
    ]))->toThrow(ValidationException::class, 'Cannot create a child ticket from another child ticket. Only root origin tickets can spawn child tickets.');

    // Policy-level assertion
    $policy = new TicketPolicy();
    $policyResponse = $policy->createChild($officer, $childTicket);
    expect($policyResponse->allowed())->toBeFalse();
    expect($policyResponse->message())->toContain('Subordinate child tickets cannot spawn further child sub-tasks');
});

test('Constraint 5: StoreTicketRequest and StoreChildTicketRequest reject soft-deleted categories', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Services']);
    $activeCategory = TicketCategory::create(['id' => 1, 'category_name' => 'Power Outage']);
    $deletedCategory = TicketCategory::create(['id' => 2, 'category_name' => 'Deprecated Category']);
    $deletedCategory->delete(); // Soft delete

    // Test StoreTicketRequest rules
    $rules = (new StoreTicketRequest())->rules();
    
    $validData = [
        'consumer_contact' => '09123456789',
        'complaint_source' => ComplaintSources::WALK_IN->value,
        'complaint_description' => 'Valid complaint description',
        'barangay' => 'San Pedro',
        'department_id' => $dept->id,
        'other_category' => false,
        'category_id' => $activeCategory->id,
    ];
    $validatorValid = Validator::make($validData, $rules);
    expect($validatorValid->passes())->toBeTrue();

    $invalidData = array_merge($validData, ['category_id' => $deletedCategory->id]);
    $validatorInvalid = Validator::make($invalidData, $rules);
    expect($validatorInvalid->passes())->toBeFalse();
    expect($validatorInvalid->errors()->has('category_id'))->toBeTrue();

    // Test StoreChildTicketRequest rules
    $childRules = (new StoreChildTicketRequest())->rules();
    $childInvalid = [
        'department_id' => $dept->id,
        'complaint_description' => 'Valid sub-task description',
        'barangay' => 'San Pedro',
        'other_category' => false,
        'category_id' => $deletedCategory->id,
    ];
    $childValidator = Validator::make($childInvalid, $childRules);
    expect($childValidator->passes())->toBeFalse();
    expect($childValidator->errors()->has('category_id'))->toBeTrue();
});

test('Constraint 6: UserService blocks deactivating field personnel while their crew has active duties', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Services']);
    $fieldUser = User::create([
        'id' => '01JUSER0000000000000000003',
        'username' => 'lineman1',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'contact' => '09123456789',
        'role_id' => 4, // field_personnel
        'department_id' => null,
        'is_active' => true,
    ]);

    $team = Team::create([
        'id' => '01JTEAM0000000000000000003',
        'team_name' => 'Alpha Crew',
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'department_id' => 1,
    ]);

    $fieldUser->teams()->attach($team->id, ['team_role_id' => 1]);

    $activeTicket = Ticket::create([
        'ticket_number' => 'TKT-261013-020',
        'team_id' => $team->id,
        'department_id' => 1,
        'complaint_description' => 'Transformer wire sparked',
        'status' => TicketStatus::IN_PROGRESS,
    ]);

    $userService = app(UserService::class);

    // Deactivation should fail because ticket is IN_PROGRESS
    expect(fn () => $userService->toggleUserStatus($fieldUser, false))
        ->toThrow(ValidationException::class, 'Cannot deactivate user while their assigned crew has active tickets assigned or in progress.');

    // Change ticket to RESOLVED; deactivation should now succeed
    $activeTicket->update(['status' => TicketStatus::RESOLVED]);
    $userService->toggleUserStatus($fieldUser, false);

    expect($fieldUser->fresh()->is_active)->toBeFalse();
});

test('Constraint 7 & 8: Crew shift validation rejects 0-min shifts, allows graveyard shifts, and prevents member overlap', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Services']);
    $lineman = User::create([
        'id' => '01JUSER0000000000000000004',
        'username' => 'lineman2',
        'first_name' => 'Pedro',
        'last_name' => 'Penduko',
        'contact' => '09123456789',
        'role_id' => 4,
        'department_id' => null,
    ]);

    // 1. Shift overlap algorithm test
    // 08:00 - 17:00 vs 17:00 - 01:00 (Back to back, no overlap)
    expect(TeamService::doShiftsOverlap('08:00', '17:00', '17:00', '01:00'))->toBeFalse();
    // 08:00 - 17:00 vs 16:30 - 01:00 (Overlaps by 30 mins)
    expect(TeamService::doShiftsOverlap('08:00', '17:00', '16:30', '01:00'))->toBeTrue();
    // Graveyard shift 22:00 - 06:00 vs 05:00 - 14:00 (Overlaps 05:00-06:00)
    expect(TeamService::doShiftsOverlap('22:00', '06:00', '05:00', '14:00'))->toBeTrue();
    // Graveyard shift 22:00 - 06:00 vs 06:00 - 14:00 (Back to back, no overlap)
    expect(TeamService::doShiftsOverlap('22:00', '06:00', '06:00', '14:00'))->toBeFalse();

    // 2. 0-minute shift rejection in StoreTeamRequest
    $rules = (new StoreTeamRequest())->rules();
    $zeroMinPayload = [
        'team_name' => 'Zero Crew',
        'shift_start' => '08:00',
        'shift_end' => '08:00', // Illogical 0-minute shift
        'department_id' => $dept->id,
    ];
    $validatorZero = Validator::make($zeroMinPayload, $rules);
    expect($validatorZero->passes())->toBeFalse();
    expect($validatorZero->errors()->has('shift_end'))->toBeTrue();

    // 3. Graveyard shift allowed in StoreTeamRequest
    $graveyardPayload = [
        'team_name' => 'Night Owl Crew',
        'shift_start' => '22:00',
        'shift_end' => '06:00', // Overnight cross-midnight shift
        'department_id' => $dept->id,
    ];
    $validatorGraveyard = Validator::make($graveyardPayload, $rules);
    expect($validatorGraveyard->errors()->has('shift_end'))->toBeFalse();

    // 4. Overlap detection across active teams for same lineman
    $teamA = Team::create([
        'id' => '01JTEAM0000000000000000004',
        'team_name' => 'Morning Crew',
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'department_id' => 1,
    ]);
    $teamA->members()->attach($lineman->id, ['team_role_id' => 1]);

    $teamService = app(TeamService::class);

    // Attempting to create Team B (16:00 - 00:00) with same lineman must fail
    expect(fn () => $teamService->createTeam([
        'team_name' => 'Evening Crew Overlap',
        'shift_start' => '16:00',
        'shift_end' => '00:00',
        'department_id' => 1,
    ], [
        ['user_id' => $lineman->id, 'team_role_id' => 1],
    ]))->toThrow(ValidationException::class);
});

test('Constraint 12: SubmitAccomplishmentReportRequest enforces aggregate 30MB payload batch limit', function () {
    $request = new SubmitAccomplishmentReportRequest();

    // Mock an oversized batch of files
    $hugeFile1 = UploadedFile::fake()->create('photo1.jpg', 16 * 1024); // 16MB
    $hugeFile2 = UploadedFile::fake()->create('photo2.jpg', 16 * 1024); // 16MB (Total 32MB > 30MB)
    $signature = UploadedFile::fake()->create('signature.png', 500);

    $validator = Validator::make([
        'remarks' => 'Completed restoration',
        'signature' => $signature,
        'photos' => [$hugeFile1, $hugeFile2],
    ], $request->rules(), $request->messages());

    // Run the withValidator callback
    $request->merge(['photos' => [$hugeFile1, $hugeFile2]]);
    $request->files->set('photos', [$hugeFile1, $hugeFile2]);
    $request->files->set('signature', $signature);
    $request->withValidator($validator);

    expect($validator->errors()->has('photos'))->toBeTrue();
    expect($validator->errors()->first('photos'))->toContain('exceeds the maximum allowed limit of 30 MB');
});

test('Constraint B-06: Endorsement state mutability freeze locks tickets in PENDING_ENDORSEMENT', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Services']);
    $lineman = User::create([
        'id' => '01JUSER0000000000000000005',
        'username' => 'lineman3',
        'first_name' => 'Jose',
        'last_name' => 'Rizal',
        'contact' => '09123456789',
        'role_id' => 4,
        'department_id' => null,
    ]);
    $supervisor = User::create([
        'id' => '01JUSER0000000000000000006',
        'username' => 'sup2',
        'first_name' => 'Maria',
        'last_name' => 'Clara',
        'contact' => '09123456789',
        'role_id' => 3,
        'department_id' => 1,
    ]);

    $team = Team::create([
        'id' => '01JTEAM0000000000000000005',
        'team_name' => 'Maintenance Crew',
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'department_id' => 1,
    ]);

    $ticket = Ticket::create([
        'ticket_number' => 'TKT-261013-050',
        'team_id' => $team->id,
        'department_id' => 1,
        'complaint_description' => 'Cross-department issue',
        'status' => TicketStatus::PENDING_ENDORSEMENT,
    ]);

    $ticketService = app(TicketService::class);
    $accomplishmentService = app(TicketAccomplishmentService::class);

    // 1. assignTicket must fail
    expect(fn () => $ticketService->assignTicket($ticket, $team->id, $supervisor))
        ->toThrow(ValidationException::class, 'Ticket is locked pending inter-departmental endorsement review.');

    // 2. startTicket must fail
    expect(fn () => $ticketService->startTicket($ticket, $lineman))
        ->toThrow(ValidationException::class, 'Ticket is locked pending inter-departmental endorsement review.');

    // 3. accomplishTicket must fail
    expect(fn () => $accomplishmentService->accomplishTicket($ticket, $lineman, [
        'remarks' => 'Fixed line',
        'signature' => UploadedFile::fake()->create('signature.png', 100),
        'photos' => [UploadedFile::fake()->create('photo.jpg', 100)],
    ]))->toThrow(ValidationException::class, 'Ticket is locked pending inter-departmental endorsement review.');

    // 4. Policy checks must deny
    $policy = new TicketPolicy();
    expect($policy->assign($supervisor, $ticket)->allowed())->toBeFalse();
    expect($policy->start($lineman, $ticket)->allowed())->toBeFalse();
    expect($policy->accomplish($lineman, $ticket)->allowed())->toBeFalse();
    expect($policy->createChild($supervisor, $ticket)->allowed())->toBeFalse();
});
