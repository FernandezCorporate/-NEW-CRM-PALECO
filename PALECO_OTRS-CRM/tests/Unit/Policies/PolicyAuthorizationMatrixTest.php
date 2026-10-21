<?php

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\ConsumerPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\TeamPolicy;
use App\Policies\TicketAccomplishmentPolicy;
use App\Policies\TicketCategoryPolicy;
use App\Policies\TicketEndorsementPolicy;
use App\Policies\TicketPolicy;
use App\Policies\TicketRemarkPolicy;
use App\Policies\UserPolicy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('UserPolicy allows only administrators full lifecycle access', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $supervisor = User::factory()->supervisor()->create();
    $policy = new UserPolicy;

    // Admin permissions
    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->view($admin))->toBeTrue();
    expect($policy->create($admin))->toBeTrue();
    expect($policy->update($admin, $supervisor))->toBeTrue();
    expect($policy->update($admin, $otherAdmin))->toBeFalse(); // Admin cannot edit another admin

    // Non-admin denials
    expect($policy->viewAny($supervisor))->toBeFalse();
    expect($policy->view($supervisor))->toBeFalse();
    expect($policy->create($supervisor))->toBeFalse();
});

test('DepartmentPolicy restricts department administrative actions strictly to admin', function () {
    $admin = User::factory()->admin()->create();
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $policy = new DepartmentPolicy;

    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->create($admin))->toBeTrue();

    expect($policy->viewAny($cwdOfficer))->toBeFalse();
    expect($policy->create($cwdOfficer))->toBeFalse();
});

test('TicketCategoryPolicy restricts category management strictly to admin', function () {
    $admin = User::factory()->admin()->create();
    $fieldPersonnel = User::factory()->fieldPersonnel()->create();
    $policy = new TicketCategoryPolicy;

    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->create($admin))->toBeTrue();

    expect($policy->viewAny($fieldPersonnel))->toBeFalse();
    expect($policy->create($fieldPersonnel))->toBeFalse();
});

test('TeamPolicy matrix enforces supervisor and admin boundaries', function () {
    $admin = User::factory()->admin()->create();
    $dept1 = Department::first();
    $dept2 = Department::factory()->create();

    $supervisor = User::factory()->supervisor()->create(['department_id' => $dept1->id]);
    $teamInDept1 = Team::factory()->create(['department_id' => $dept1->id]);
    $teamInDept2 = Team::factory()->create(['department_id' => $dept2->id]);

    $policy = new TeamPolicy;

    // Web permissions
    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->viewAny($supervisor))->toBeFalse();

    // Mobile supervisor permissions
    expect($policy->create($supervisor))->toBeTrue();
    expect($policy->viewAnyDepartmentTeams($supervisor))->toBeTrue();

    // Cross-department access
    expect($policy->viewDepartmentTeams($supervisor, $teamInDept1)->allowed())->toBeTrue();
    expect($policy->viewDepartmentTeams($supervisor, $teamInDept2)->allowed())->toBeFalse();
});

test('TicketPolicy defines proper domain access for CWD Officers and Supervisors', function () {
    $dept = Department::first();
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $supervisor = User::factory()->supervisor()->create(['department_id' => $dept->id]);
    $ticket = Ticket::factory()->create([
        'department_id' => $dept->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $policy = new TicketPolicy;

    // CWD Officer permissions
    expect($policy->create($cwdOfficer))->toBeTrue();
    expect($policy->cancel($cwdOfficer, $ticket)->allowed())->toBeTrue();
    expect($policy->update($cwdOfficer, $ticket)->allowed())->toBeTrue();

    // Supervisor permissions
    expect($policy->create($supervisor))->toBeFalse();
    expect($policy->cancel($supervisor, $ticket)->allowed())->toBeFalse();
    expect($policy->assign($supervisor, $ticket)->allowed())->toBeTrue();
    expect($policy->endorse($supervisor, $ticket)->allowed())->toBeTrue();
});

test('TicketAccomplishmentPolicy and TicketEndorsementPolicy allow only CWD Officers', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $supervisor = User::factory()->supervisor()->create();

    $accomplishmentPolicy = new TicketAccomplishmentPolicy;
    expect($accomplishmentPolicy->view($cwdOfficer))->toBeTrue();
    expect($accomplishmentPolicy->view($supervisor))->toBeFalse();

    $endorsementPolicy = new TicketEndorsementPolicy;
    expect($endorsementPolicy->viewAny($cwdOfficer))->toBeTrue();
    expect($endorsementPolicy->decide($cwdOfficer))->toBeTrue();
    expect($endorsementPolicy->viewAny($supervisor))->toBeFalse();
    expect($endorsementPolicy->decide($supervisor))->toBeFalse();
});

test('TicketRemarkPolicy governs web and mobile commenting permissions', function () {
    $dept = Department::first();
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $supervisor = User::factory()->supervisor()->create(['department_id' => $dept->id]);
    $team = Team::factory()->create(['department_id' => $dept->id]);
    $worker = User::factory()->fieldPersonnel()->create();
    $role = TeamRole::first();
    $team->members()->attach($worker->id, ['team_role_id' => $role->id]);

    $ticket = Ticket::factory()->create([
        'department_id' => $dept->id,
        'team_id' => $team->id,
    ]);

    $policy = new TicketRemarkPolicy;

    // Web remarks
    expect($policy->create($cwdOfficer, $ticket)->allowed())->toBeTrue();
    expect($policy->create($supervisor, $ticket)->allowed())->toBeFalse();

    // Mobile remarks
    expect($policy->mobileCreate($supervisor, $ticket)->allowed())->toBeTrue();
    expect($policy->mobileCreate($worker, $ticket)->allowed())->toBeTrue();

    // Worker not in team
    $otherWorker = User::factory()->fieldPersonnel()->create();
    expect($policy->mobileCreate($otherWorker, $ticket)->allowed())->toBeFalse();
});

test('ConsumerPolicy and ActivityPolicy restrict sensitive directory and forensics', function () {
    $admin = User::factory()->admin()->create();
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $supervisor = User::factory()->supervisor()->create();

    $consumerPolicy = new ConsumerPolicy;
    expect($consumerPolicy->viewAny($admin))->toBeTrue();
    expect($consumerPolicy->viewAny($cwdOfficer))->toBeTrue();
    expect($consumerPolicy->viewAny($supervisor))->toBeFalse();

    $activityPolicy = new ActivityPolicy;
    $activity = new Activity;
    expect($activityPolicy->viewAny($admin))->toBeTrue();
    expect($activityPolicy->view($admin, $activity))->toBeTrue();
    expect($activityPolicy->viewAny($cwdOfficer))->toBeFalse();
    expect($activityPolicy->viewAny($supervisor))->toBeFalse();
});
