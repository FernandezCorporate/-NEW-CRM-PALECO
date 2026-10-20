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

test('field personnel can be scheduled to multiple teams with overlapping shifts', function () {
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

    // Team 2 attempt: 12:00 - 20:00 (overlapping shift allowed)
    $payload = [
        'team_name' => 'Concurrent Team',
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

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('admin.teams'));

    $newTeam = Team::where('team_name', 'Concurrent Team')->first();
    expect($newTeam)->not->toBeNull();
    expect($newTeam->members->pluck('id'))->toContain($fieldUser->id);
    expect($fieldUser->fresh()->teams)->toHaveCount(2);
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
    $response->assertSessionHas('error', 'Cannot archive team. It still has tickets that are assigned, in progress, awaiting report verification, or pending endorsement.');
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

test('personnel options are sorted alphabetically despite encrypted names', function () {
    $department = Department::first();
    $supervisor = User::factory()->supervisor()->create(['department_id' => $department->id]);

    User::factory()->fieldPersonnel()->create(['first_name' => 'zaldy', 'last_name' => 'reyes']);
    User::factory()->fieldPersonnel()->create(['first_name' => 'andres', 'last_name' => 'santos']);
    User::factory()->fieldPersonnel()->create(['first_name' => 'marites', 'last_name' => 'cruz']);

    Sanctum::actingAs($supervisor);

    $personnel = $this->getJson('/api/v1/teams/team-options')
        ->assertOk()
        ->json('data.personnel');

    // Must stay a JSON list (not an object keyed by original positions) for the mobile app
    expect(array_is_list($personnel))->toBeTrue();

    $firstNames = array_column($personnel, 'first_name');
    $sorted = $firstNames;
    sort($sorted, SORT_STRING | SORT_FLAG_CASE);

    expect($firstNames)->toBe($sorted)
        ->and($firstNames)->toContain('andres', 'marites', 'zaldy');
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

test('emptying the roster of a team with active tickets is rejected', function (TicketStatus $status) {
    $admin = User::factory()->admin()->create();
    $department = Department::first();
    $team = Team::factory()->create(['department_id' => $department->id, 'shift_start' => '08:00', 'shift_end' => '17:00']);
    $member = User::factory()->fieldPersonnel()->create();
    $team->members()->attach($member->id, ['team_role_id' => TeamRole::first()->id]);
    $ticket = Ticket::factory()->create([
        'team_id' => $team->id,
        'department_id' => $department->id,
        'status' => $status->value,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.teams.update', $team), [
        'team_name' => $team->team_name,
        'department_id' => $department->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'original_updated_at' => $team->updated_at->toIso8601String(),
    ]);

    $response->assertSessionHas('error', fn ($message) => str_contains($message, $ticket->ticket_number));
    expect($team->fresh()->members)->toHaveCount(1);
})->with([
    'assigned' => [TicketStatus::ASSIGNED],
    'in progress' => [TicketStatus::IN_PROGRESS],
    'resolved' => [TicketStatus::RESOLVED],
    'pending endorsement' => [TicketStatus::PENDING_ENDORSEMENT],
]);

test('a team whose tickets are all closed or cancelled can be emptied', function () {
    $admin = User::factory()->admin()->create();
    $department = Department::first();
    $team = Team::factory()->create(['department_id' => $department->id, 'shift_start' => '08:00', 'shift_end' => '17:00']);
    $member = User::factory()->fieldPersonnel()->create();
    $team->members()->attach($member->id, ['team_role_id' => TeamRole::first()->id]);
    foreach ([TicketStatus::CLOSED, TicketStatus::CANCELLED] as $status) {
        Ticket::factory()->create(['team_id' => $team->id, 'department_id' => $department->id, 'status' => $status->value]);
    }

    $this->actingAs($admin)->put(route('admin.teams.update', $team), [
        'team_name' => $team->team_name,
        'department_id' => $department->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'original_updated_at' => $team->updated_at->toIso8601String(),
    ])->assertSessionHas('success');

    expect($team->fresh()->members)->toHaveCount(0);
});

test('removing some members of a busy team is still allowed', function () {
    $admin = User::factory()->admin()->create();
    $department = Department::first();
    $role = TeamRole::first();
    $team = Team::factory()->create(['department_id' => $department->id, 'shift_start' => '08:00', 'shift_end' => '17:00']);
    [$keep, $remove] = User::factory()->fieldPersonnel()->count(2)->create();
    $team->members()->attach([$keep->id => ['team_role_id' => $role->id], $remove->id => ['team_role_id' => $role->id]]);
    Ticket::factory()->create(['team_id' => $team->id, 'department_id' => $department->id, 'status' => TicketStatus::IN_PROGRESS->value]);

    $this->actingAs($admin)->put(route('admin.teams.update', $team), [
        'team_name' => $team->team_name,
        'department_id' => $department->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'original_updated_at' => $team->updated_at->toIso8601String(),
        'members' => [['user_id' => $keep->id, 'team_role_id' => $role->id]],
    ])->assertSessionHas('success');

    expect($team->fresh()->members->pluck('id')->all())->toBe([$keep->id]);
});

test('mobile team update without members keeps the roster', function () {
    $department = Department::first();
    $supervisor = User::factory()->supervisor()->create(['department_id' => $department->id]);
    $team = Team::factory()->create(['department_id' => $department->id, 'shift_start' => '08:00', 'shift_end' => '17:00']);
    $member = User::factory()->fieldPersonnel()->create();
    $team->members()->attach($member->id, ['team_role_id' => TeamRole::first()->id]);

    Sanctum::actingAs($supervisor);

    $this->putJson("/api/v1/teams/{$team->id}", [
        'team_name' => 'Renamed Crew',
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'original_updated_at' => $team->updated_at->toIso8601String(),
    ])->assertOk();

    $team->refresh();
    expect($team->team_name)->toBe('Renamed Crew')
        ->and($team->members->pluck('id')->all())->toBe([$member->id]);
});

test('mobile team update with an empty members list on a busy team returns 422', function () {
    $department = Department::first();
    $supervisor = User::factory()->supervisor()->create(['department_id' => $department->id]);
    $team = Team::factory()->create(['department_id' => $department->id, 'shift_start' => '08:00', 'shift_end' => '17:00']);
    $member = User::factory()->fieldPersonnel()->create();
    $team->members()->attach($member->id, ['team_role_id' => TeamRole::first()->id]);
    $ticket = Ticket::factory()->create(['team_id' => $team->id, 'department_id' => $department->id, 'status' => TicketStatus::ASSIGNED->value]);

    Sanctum::actingAs($supervisor);

    $this->putJson("/api/v1/teams/{$team->id}", [
        'team_name' => $team->team_name,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'original_updated_at' => $team->updated_at->toIso8601String(),
        'members' => [],
    ])->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonFragment(['message' => "Cannot remove every member while the team still has active tickets: {$ticket->ticket_number}. Reassign or finish them first."]);

    expect($team->fresh()->members)->toHaveCount(1);
});

test('a deactivated user cannot be added to a team', function (string $channel, string $action) {
    $department = Department::first();
    $role = TeamRole::first();
    $inactive = User::factory()->fieldPersonnel()->inactive()->create();
    $team = Team::factory()->create(['department_id' => $department->id, 'shift_start' => '08:00', 'shift_end' => '17:00']);

    $payload = [
        'team_name' => 'Charlie Crew',
        'department_id' => $department->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'members' => [['user_id' => $inactive->id, 'team_role_id' => $role->id]],
    ];
    if ($action === 'update') {
        $payload['original_updated_at'] = $team->updated_at->toIso8601String();
    }

    if ($channel === 'web') {
        $this->actingAs(User::factory()->admin()->create());
        $response = $action === 'store'
            ? $this->post(route('admin.teams.store'), $payload)
            : $this->put(route('admin.teams.update', $team), $payload);

        $response->assertSessionHasErrors(['members.0.user_id' => 'One or more of the selected members are not active Field Personnel.']);
    } else {
        Sanctum::actingAs(User::factory()->supervisor()->create(['department_id' => $department->id]));
        $response = $action === 'store'
            ? $this->postJson('/api/v1/teams', $payload)
            : $this->putJson("/api/v1/teams/{$team->id}", $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['members.0.user_id']);
    }

    expect($inactive->fresh()->teams()->count())->toBe(0);
})->with([
    'web create' => ['web', 'store'],
    'web update' => ['web', 'update'],
    'mobile create' => ['mobile', 'store'],
    'mobile update' => ['mobile', 'update'],
]);
