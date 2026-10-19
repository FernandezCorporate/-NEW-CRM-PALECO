<?php

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('admin can view team management dashboard', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.teams'));

    $response->assertStatus(200);
    $response->assertViewIs('admin.pages.teamManagement');
    $response->assertViewHas('teams');
});

test('non-admin users cannot access web team management dashboard', function () {
    $supervisor = User::factory()->supervisor()->create();
    $fieldPersonnel = User::factory()->fieldPersonnel()->create();

    $this->actingAs($supervisor)->get(route('admin.teams'))
        ->assertStatus(404);

    $this->actingAs($fieldPersonnel)->get(route('admin.teams'))
        ->assertStatus(404);
});

test('admin can view team details and create/edit forms', function () {
    $admin = User::factory()->admin()->create();
    $department = Department::first();
    $team = Team::factory()->create(['department_id' => $department->id]);

    $this->actingAs($admin)->get(route('admin.teams.show', $team))
        ->assertStatus(200)
        ->assertViewIs('admin.pages.teamDetails')
        ->assertViewHas('team');

    $this->actingAs($admin)->get(route('admin.teams.createForm'))
        ->assertStatus(200)
        ->assertViewIs('admin.forms.teamForm');

    $this->actingAs($admin)->get(route('admin.teams.editForm', $team))
        ->assertStatus(200)
        ->assertViewIs('admin.forms.teamForm');
});

test('attempting to edit an archived team redirects with error', function () {
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->create();
    $team->delete();

    $response = $this->actingAs($admin)->get(route('admin.teams.editForm', $team));

    $response->assertRedirect(route('admin.teams'));
    $response->assertSessionHas('error', 'The team you are trying to edit has been archived by another administrator.');
});

test('admin can create a new team with valid members and shift times', function () {
    $admin = User::factory()->admin()->create();
    $department = Department::first();
    $fieldUser = User::factory()->fieldPersonnel()->create(['department_id' => $department->id]);
    $teamRole = TeamRole::first();

    $payload = [
        'team_name' => 'Delta Emergency Crew',
        'team_desc' => 'High priority delta response unit',
        'department_id' => $department->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'members' => [
            [
                'user_id' => $fieldUser->id,
                'team_role_id' => $teamRole->id,
            ],
        ],
    ];

    $response = $this->actingAs($admin)->post(route('admin.teams.store'), $payload);

    $response->assertRedirect(route('admin.teams'));
    $response->assertSessionHas('success', 'Team and members created successfully.');

    $this->assertDatabaseHas('teams', [
        'team_name' => 'Delta Emergency Crew',
        'department_id' => $department->id,
    ]);

    $createdTeam = Team::where('team_name', 'Delta Emergency Crew')->first();
    expect($createdTeam->members)->toHaveCount(1);
    expect($createdTeam->members->first()->id)->toBe($fieldUser->id);
});

test('team creation fails when shift start and end times are identical', function () {
    $admin = User::factory()->admin()->create();
    $department = Department::first();

    $payload = [
        'team_name' => 'Zero Hour Crew',
        'department_id' => $department->id,
        'shift_start' => '08:00',
        'shift_end' => '08:00',
    ];

    $response = $this->actingAs($admin)->post(route('admin.teams.store'), $payload);

    $response->assertSessionHasErrors(['shift_end']);
});

test('team creation fails when team name already exists in the same department', function () {
    $admin = User::factory()->admin()->create();
    $department = Department::first();
    Team::factory()->create([
        'team_name' => 'Alpha Crew',
        'department_id' => $department->id,
    ]);

    $payload = [
        'team_name' => 'Alpha Crew',
        'department_id' => $department->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
    ];

    $response = $this->actingAs($admin)->post(route('admin.teams.store'), $payload);

    $response->assertSessionHasErrors(['team_name']);
});

test('member shift overlap validation prevents scheduling user to conflicting shifts', function () {
    $admin = User::factory()->admin()->create();
    $department = Department::first();
    $fieldUser = User::factory()->fieldPersonnel()->create(['department_id' => $department->id]);
    $teamRole = TeamRole::first();

    // Team 1: 08:00 - 17:00
    $team1 = Team::factory()->create([
        'department_id' => $department->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
    ]);
    $team1->members()->attach($fieldUser->id, ['team_role_id' => $teamRole->id]);

    // Team 2 attempt: 12:00 - 20:00 (overlaps with Team 1)
    $payload = [
        'team_name' => 'Conflicting Team',
        'department_id' => $department->id,
        'shift_start' => '12:00',
        'shift_end' => '20:00',
        'members' => [
            [
                'user_id' => $fieldUser->id,
                'team_role_id' => $teamRole->id,
            ],
        ],
    ];

    $response = $this->actingAs($admin)->post(route('admin.teams.store'), $payload);

    $response->assertSessionHasErrors();
});

test('admin can update team details and members with concurrency timestamp', function () {
    $admin = User::factory()->admin()->create();
    $department = Department::first();
    $team = Team::factory()->create([
        'team_name' => 'Original Name',
        'department_id' => $department->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
    ]);

    $payload = [
        'team_name' => 'Updated Team Name',
        'department_id' => $department->id,
        'shift_start' => '09:00',
        'shift_end' => '18:00',
        'original_updated_at' => $team->updated_at->toIso8601String(),
    ];

    $response = $this->actingAs($admin)->put(route('admin.teams.update', $team), $payload);

    $response->assertRedirect(route('admin.teams'));
    $response->assertSessionHas('success', 'Team updated successfully.');

    expect($team->fresh()->team_name)->toBe('Updated Team Name');
});

test('team update detects concurrency collision with stale timestamp', function () {
    $admin = User::factory()->admin()->create();
    $department = Department::first();
    $team = Team::factory()->create([
        'department_id' => $department->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
    ]);

    $staleTime = $team->updated_at->subMinutes(5)->toIso8601String();

    $payload = [
        'team_name' => 'Collision Name',
        'department_id' => $department->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'original_updated_at' => $staleTime,
    ];

    $response = $this->actingAs($admin)->put(route('admin.teams.update', $team), $payload);

    $response->assertSessionHas('error');
    expect($team->fresh()->team_name)->not->toBe('Collision Name');
});

test('admin can archive, restore, and force-delete an inactive team', function () {
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->create();

    // Delete confirmation prompt
    $this->actingAs($admin)->get(route('admin.teams.deleteConfirm', $team))
        ->assertStatus(200)
        ->assertViewIs('admin.prompts.teamDeleteConfirm');

    // Archive
    $archiveResponse = $this->actingAs($admin)->delete(route('admin.teams.archive', $team));
    $archiveResponse->assertRedirect(route('admin.teams'));
    $archiveResponse->assertSessionHas('success', 'Team archived successfully.');
    expect($team->fresh()->trashed())->toBeTrue();

    // Restore
    $restoreResponse = $this->actingAs($admin)->patch(route('admin.teams.restore', $team));
    $restoreResponse->assertRedirect(route('admin.teams'));
    $restoreResponse->assertSessionHas('success', 'Team restored successfully.');
    expect($team->fresh()->trashed())->toBeFalse();

    // Re-archive and permanently force delete
    $this->actingAs($admin)->delete(route('admin.teams.archive', $team));
    $destroyResponse = $this->actingAs($admin)->delete(route('admin.teams.destroy', $team));
    $destroyResponse->assertRedirect(route('admin.teams'));
    $destroyResponse->assertSessionHas('success', 'Team permanently deleted successfully.');
    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

test('archiving team is prevented if team has active assigned tickets', function () {
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->create();

    Ticket::factory()->create([
        'team_id' => $team->id,
        'status' => TicketStatus::ASSIGNED->value,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.teams.archive', $team));

    $response->assertRedirect(route('admin.teams'));
    $response->assertSessionHas('error', 'Cannot archive team. They currently have active assigned service tickets.');
    expect($team->fresh()->trashed())->toBeFalse();
});

test('mobile supervisor can retrieve team options and list department teams', function () {
    $department = Department::first();
    $supervisor = User::factory()->supervisor()->create(['department_id' => $department->id]);
    Team::factory()->create(['department_id' => $department->id]);

    Sanctum::actingAs($supervisor);

    $optionsResponse = $this->getJson('/api/v1/teams/team-options');
    $optionsResponse->assertStatus(200)
        ->assertJson(['success' => true]);

    $listResponse = $this->getJson('/api/v1/teams');
    $listResponse->assertStatus(200)
        ->assertJson(['success' => true]);
});

test('mobile supervisor cannot access teams from another department', function () {
    $dept1 = Department::first();
    $dept2 = Department::factory()->create(['dept_name' => 'Other Dept']);
    $supervisor = User::factory()->supervisor()->create(['department_id' => $dept1->id]);
    $teamInOtherDept = Team::factory()->create(['department_id' => $dept2->id]);

    Sanctum::actingAs($supervisor);

    $response = $this->getJson("/api/v1/teams/{$teamInOtherDept->id}");
    $response->assertStatus(404);
});
