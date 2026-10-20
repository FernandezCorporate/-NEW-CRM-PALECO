<?php

use App\Actions\Teams\ArchiveTeam;
use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\ForceDeleteTeam;
use App\Actions\Teams\RestoreTeam;
use App\Actions\Teams\UpdateTeam;
use App\Actions\Users\CreateUser;
use App\Actions\Users\ResetUserPassword;
use App\Actions\Users\ToggleUserActiveStatus;
use App\Actions\Users\UnlockUser;
use App\Enums\TicketStatus;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('CreateUser strips department if role is field personnel', function () {
    $dept = Department::first();
    $fieldRole = AccountRole::where('slug_identifier', 'field_personnel')->first();

    $action = app(CreateUser::class);
    $user = $action->execute([
        'username' => 'lineman_action_test',
        'email' => 'lineman_action_test@paleco.org.ph',
        'password' => 'SecurePass123!',
        'first_name' => 'Mario',
        'last_name' => 'Cruz',
        'contact' => '09171234567',
        'role_id' => $fieldRole->id,
        'department_id' => $dept->id,
    ]);

    expect($user->department_id)->toBeNull();
    expect($user->role_id)->toBe($fieldRole->id);
});

test('ToggleUserActiveStatus deactivates user, revoking tokens and team associations', function () {
    $team = Team::factory()->create();
    $user = User::factory()->fieldPersonnel()->create();
    $teamRole = TeamRole::first();
    $team->members()->attach($user->id, ['team_role_id' => $teamRole->id]);

    $user->createToken('test-mobile-token');
    expect($user->tokens)->toHaveCount(1);
    expect($user->teams)->toHaveCount(1);

    $action = app(ToggleUserActiveStatus::class);
    $action->execute($user, false);

    expect($user->fresh()->is_active)->toBeFalse();
    expect($user->fresh()->tokens)->toHaveCount(0);
    expect($user->fresh()->teams)->toHaveCount(0);
});

test('ToggleUserActiveStatus blocks deactivating personnel with active tickets in progress', function () {
    $team = Team::factory()->create();
    $user = User::factory()->fieldPersonnel()->create();
    $teamRole = TeamRole::first();
    $team->members()->attach($user->id, ['team_role_id' => $teamRole->id]);

    Ticket::factory()->create([
        'team_id' => $team->id,
        'status' => TicketStatus::IN_PROGRESS->value,
    ]);

    $action = app(ToggleUserActiveStatus::class);

    expect(fn () => $action->execute($user, false))->toThrow(ValidationException::class);
    expect($user->fresh()->is_active)->toBeTrue();
});

test('ResetUserPassword updates password, clears lockout, and revokes sessions', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $user = User::factory()->locked()->create([
        'password' => 'OldPassword123!',
    ]);

    $key = 'auth:account:locked:'.strtolower($user->username);
    RateLimiter::hit($key, 900);

    $action = app(ResetUserPassword::class);
    $action->execute($user, 'BrandNewPassword123!');

    $user->refresh();
    expect(Hash::check('BrandNewPassword123!', $user->password))->toBeTrue();
    expect($user->locked_until)->toBeNull();
    expect(RateLimiter::tooManyAttempts($key, 1))->toBeFalse();
});

test('UnlockUser bypasses lockout and clears rate limiters', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $user = User::factory()->locked()->create();
    $key = 'auth:account:locked:'.strtolower($user->username);
    RateLimiter::hit($key, 900);

    $action = app(UnlockUser::class);
    $action->execute($user);

    expect($user->fresh()->locked_until)->toBeNull();
    expect(RateLimiter::tooManyAttempts($key, 1))->toBeFalse();
});

test('CreateTeam creates team and synchronizes members', function () {
    $dept = Department::first();
    $user1 = User::factory()->fieldPersonnel()->create();
    $role = TeamRole::first();

    $action = app(CreateTeam::class);
    $team = $action->execute([
        'team_name' => 'Bravo Dispatch Crew',
        'department_id' => $dept->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
    ], [
        ['user_id' => $user1->id, 'team_role_id' => $role->id],
    ]);

    expect($team)->toBeInstanceOf(Team::class);
    expect($team->team_name)->toBe('Bravo Dispatch Crew');
    expect($team->members)->toHaveCount(1);
});

test('UpdateTeam detects optimistic concurrency collision', function () {
    $team = Team::factory()->create();
    $staleTimestamp = $team->updated_at->subMinutes(5)->toIso8601String();

    $action = app(UpdateTeam::class);
    $result = $action->execute($team, [
        'team_name' => 'Collision Name',
        'original_updated_at' => $staleTimestamp,
    ], []);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('Conflict: This team was modified by another user');
});

test('ArchiveTeam archives empty team and prevents archiving if tickets active', function () {
    $team = Team::factory()->create();

    $action = app(ArchiveTeam::class);

    // Should succeed on empty team
    $result = $action->execute($team);
    expect($result['success'])->toBeTrue();
    expect($team->fresh()->trashed())->toBeTrue();

    // Restore and assign active ticket
    $team->restore();
    Ticket::factory()->create([
        'team_id' => $team->id,
        'status' => TicketStatus::ASSIGNED->value,
    ]);

    $blockedResult = $action->execute($team);
    expect($blockedResult['success'])->toBeFalse();
    expect($blockedResult['message'])->toContain('Cannot archive team. It still has tickets that are assigned, in progress, awaiting report verification, or pending endorsement.');
    expect($team->fresh()->trashed())->toBeFalse();
});

test('ArchiveTeam is blocked while a ticket can still return to the team', function (TicketStatus $status) {
    $team = Team::factory()->create();
    Ticket::factory()->create([
        'team_id' => $team->id,
        'department_id' => $team->department_id,
        'status' => $status->value,
    ]);

    $result = app(ArchiveTeam::class)->execute($team);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Cannot archive team')
        ->and($team->fresh()->trashed())->toBeFalse();
})->with([
    'resolved (report awaiting verification)' => [TicketStatus::RESOLVED],
    'pending endorsement' => [TicketStatus::PENDING_ENDORSEMENT],
]);

test('ArchiveTeam still archives a team whose tickets are all closed or cancelled', function () {
    $team = Team::factory()->create();
    foreach ([TicketStatus::CLOSED, TicketStatus::CANCELLED] as $status) {
        Ticket::factory()->create([
            'team_id' => $team->id,
            'department_id' => $team->department_id,
            'status' => $status->value,
        ]);
    }

    $result = app(ArchiveTeam::class)->execute($team);

    expect($result['success'])->toBeTrue()
        ->and($team->fresh()->trashed())->toBeTrue();
});

test('RestoreTeam restores archived team and ForceDeleteTeam purges it', function () {
    $team = Team::factory()->create();
    $team->delete();

    $restoreAction = app(RestoreTeam::class);
    $restoreResult = $restoreAction->execute($team);
    expect($restoreResult['success'])->toBeTrue();
    expect($team->fresh()->trashed())->toBeFalse();

    // Re-archive and permanently force-delete
    $team->delete();
    $forceAction = app(ForceDeleteTeam::class);
    $forceResult = $forceAction->execute($team);
    expect($forceResult['success'])->toBeTrue();
    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

