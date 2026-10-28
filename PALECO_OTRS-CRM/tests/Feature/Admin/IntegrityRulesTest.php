<?php

use App\Enums\TicketStatus;
use App\Models\AccomplishmentPhoto;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\TicketRemark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
    $this->cwd = User::where('username', 'alliah')->first();
    $this->admin = User::where('username', 'allenglenn')->first();
    $this->dept = Department::first();
    $this->archivedDept = Department::factory()->create(['dept_name' => 'Retired Division']);
    $this->archivedDept->delete();
});

function intakePayload(int $departmentId): array
{
    return [
        'consumer_contact' => '09123456789',
        'complaint_source' => 'phone_call',
        'complaint_description' => 'No power in the area',
        'purok' => 'Purok 1',
        'street' => 'Rizal Street',
        'barangay' => 'Poblacion',
        'department_id' => $departmentId,
        'other_category' => false,
        'category_id' => TicketCategory::first()->id,
        'link_consumer' => false,
    ];
}

// --- H1: tickets can't go to an archived department ---

test('a ticket cannot be created, edited or spun off into an archived department', function () {
    $this->actingAs($this->cwd)
        ->post(route('cwd.tickets.store'), intakePayload($this->archivedDept->id))
        ->assertSessionHasErrors(['department_id' => 'The selected department does not exist or has been archived.']);

    $open = Ticket::factory()->open()->create(['department_id' => $this->dept->id]);
    $this->actingAs($this->cwd)
        ->put(route('cwd.tickets.update', $open), intakePayload($this->archivedDept->id) + ['original_updated_at' => $open->updated_at->toIso8601String()])
        ->assertSessionHasErrors('department_id');

    $child = intakePayload($this->archivedDept->id);
    unset($child['complaint_source'], $child['link_consumer']);
    $this->actingAs($this->cwd)
        ->post(route('cwd.tickets.children.store', $open), $child)
        ->assertSessionHasErrors('department_id');

    expect(Ticket::where('department_id', $this->archivedDept->id)->exists())->toBeFalse()
        ->and($open->fresh()->department_id)->toBe($this->dept->id);
});

// --- H3 and M1: team departments ---

test('a team with active tickets cannot move to another department, an idle one can', function () {
    $other = Department::skip(1)->first();
    $role = TeamRole::first();
    $team = Team::factory()->create(['department_id' => $this->dept->id, 'shift_start' => '08:00', 'shift_end' => '17:00']);
    $member = User::factory()->fieldPersonnel()->create();
    $team->members()->attach($member->id, ['team_role_id' => $role->id]);
    $ticket = Ticket::factory()->create(['department_id' => $this->dept->id, 'team_id' => $team->id, 'status' => TicketStatus::IN_PROGRESS->value]);

    $payload = fn () => [
        'team_name' => $team->team_name,
        'department_id' => $other->id,
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'original_updated_at' => $team->fresh()->updated_at->toIso8601String(),
        'members' => [['user_id' => $member->id, 'team_role_id' => $role->id]],
    ];

    $this->actingAs($this->admin)->put(route('admin.teams.update', $team), $payload())
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'Cannot move this team') && str_contains($message, $ticket->ticket_number));
    expect($team->fresh()->department_id)->toBe($this->dept->id);

    $ticket->update(['status' => TicketStatus::CLOSED->value]);
    $this->actingAs($this->admin)->put(route('admin.teams.update', $team), $payload())->assertSessionHas('success');
    expect($team->fresh()->department_id)->toBe($other->id);
});

test('a team cannot be created in or moved into an archived department', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.teams.store'), ['team_name' => 'Ghost Crew', 'department_id' => $this->archivedDept->id, 'shift_start' => '08:00', 'shift_end' => '17:00'])
        ->assertSessionHasErrors(['department_id' => 'The selected department is invalid or has been archived.']);

    $team = Team::factory()->create(['department_id' => $this->dept->id, 'shift_start' => '08:00', 'shift_end' => '17:00']);
    $this->actingAs($this->admin)
        ->put(route('admin.teams.update', $team), [
            'team_name' => $team->team_name, 'department_id' => $this->archivedDept->id, 'shift_start' => '08:00', 'shift_end' => '17:00',
            'original_updated_at' => $team->updated_at->toIso8601String(),
        ])
        ->assertSessionHasErrors('department_id');

    expect(Team::where('team_name', 'Ghost Crew')->exists())->toBeFalse()
        ->and($team->fresh()->department_id)->toBe($this->dept->id);
});

// --- H2, M3, M4: force deletes and restores ---

test('a team that ever handled a ticket cannot be force-deleted, an unused one can', function () {
    $used = Team::factory()->create(['department_id' => $this->dept->id]);
    $current = Team::factory()->create(['department_id' => $this->dept->id]);
    $ticket = Ticket::factory()->create(['department_id' => $this->dept->id, 'team_id' => $current->id, 'status' => TicketStatus::CLOSED->value]);
    TicketAssignment::create(['ticket_id' => $ticket->id, 'team_id' => $used->id, 'assigned_by_id' => $this->admin->id, 'unassigned_at' => now()]);
    $used->delete();

    $this->actingAs($this->admin)->delete(route('admin.teams.destroy', $used->id))
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'Keep it archived instead'));
    expect(Team::withTrashed()->find($used->id))->not->toBeNull()
        ->and(TicketAssignment::where('team_id', $used->id)->count())->toBe(1);

    $mistake = Team::factory()->create(['department_id' => $this->dept->id]);
    $mistake->delete();
    $this->actingAs($this->admin)->delete(route('admin.teams.destroy', $mistake->id))->assertSessionHas('success');
    expect(Team::withTrashed()->find($mistake->id))->toBeNull();
});

test('a team cannot be restored while its department is archived', function () {
    $dept = Department::factory()->create();
    $team = Team::factory()->create(['department_id' => $dept->id]);
    $team->delete();
    $dept->delete();

    $this->actingAs($this->admin)->patch(route('admin.teams.restore', $team->id))
        ->assertSessionHas('error', 'Cannot restore team. Its department is archived; restore the department first.');
    expect(Team::find($team->id))->toBeNull();

    $supervisor = User::factory()->supervisor($dept->id)->create();
    Sanctum::actingAs($supervisor, ['*']);
    $this->patchJson("/api/v1/teams/{$team->id}/restore")->assertStatus(409);
});

test('a department that anything ever referenced cannot be force-deleted', function (string $reference) {
    $dept = Department::factory()->create();
    match ($reference) {
        'archived team' => Team::factory()->create(['department_id' => $dept->id])->delete(),
        'inactive supervisor' => User::factory()->supervisor($dept->id)->inactive()->create(),
        'closed ticket' => Ticket::factory()->create(['department_id' => $dept->id, 'status' => TicketStatus::CLOSED->value]),
    };
    $dept->delete();

    $this->actingAs($this->admin)->delete(route('admin.departments.destroy', $dept->id))
        ->assertSessionHas('error', 'Cannot permanently delete this department. It contains historical data.');
    expect(Department::withTrashed()->find($dept->id))->not->toBeNull();
})->with(['archived team', 'inactive supervisor', 'closed ticket']);

test('a department created by mistake can still be force-deleted', function () {
    $dept = Department::factory()->create();
    $dept->delete();

    $this->actingAs($this->admin)->delete(route('admin.departments.destroy', $dept->id))->assertSessionHas('success');
    expect(Department::withTrashed()->find($dept->id))->toBeNull();
});

// --- M2 and L5: user activation ---

test('a supervisor of an archived department cannot be reactivated', function () {
    $supervisor = User::factory()->supervisor($this->archivedDept->id)->inactive()->create();

    $this->actingAs($this->admin)
        ->from(route('admin.users.reactivateConfirm', $supervisor))
        ->followingRedirects()
        ->patch(route('admin.users.reactivate', $supervisor))
        ->assertOk()
        ->assertSee('Cannot reactivate this account because its department is archived.');

    expect($supervisor->fresh()->is_active)->toBeFalse();
});

test('the last active account of a role cannot be deactivated', function () {
    expect(User::whereHas('role', fn ($q) => $q->where('slug_identifier', 'cwd_officer'))->where('is_active', true)->count())->toBe(1);

    $this->actingAs($this->admin)
        ->from(route('admin.users.deactivateConfirm', $this->cwd))
        ->followingRedirects()
        ->patch(route('admin.users.deactivate', $this->cwd))
        ->assertOk()
        ->assertSee('Cannot deactivate the last active CWD Officer account.');

    expect($this->cwd->fresh()->is_active)->toBeTrue();

    User::factory()->cwdOfficer()->create();
    $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $this->cwd))->assertSessionHas('success');
    expect($this->cwd->fresh()->is_active)->toBeFalse();
});

// --- L1: closed and cancelled tickets are read-only ---

test('remarks cannot be posted on closed or cancelled tickets', function (TicketStatus $status) {
    $team = Team::first();
    $worker = User::factory()->fieldPersonnel()->create();
    $team->members()->attach($worker->id, ['team_role_id' => TeamRole::first()->id]);
    $ticket = Ticket::factory()->create(['department_id' => $team->department_id, 'team_id' => $team->id, 'status' => $status->value]);

    $this->actingAs($this->cwd)
        ->post(route('shared.tickets.remarks.store', $ticket), ['body' => 'Late note on a finished ticket.'])
        ->assertForbidden();

    Sanctum::actingAs($worker, ['*']);
    $this->postJson("/api/v1/tickets/{$ticket->id}/remarks", ['body' => 'Late note from the field.'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Closed and cancelled tickets are read-only. Remarks can no longer be added.');

    expect(TicketRemark::where('ticket_id', $ticket->id)->exists())->toBeFalse();
})->with([
    'closed' => [TicketStatus::CLOSED],
    'cancelled' => [TicketStatus::CANCELLED],
]);

// --- L2: category archive ---

test('a category can be archived only when all its tickets are closed or cancelled', function (TicketStatus $status) {
    $category = TicketCategory::factory()->create();
    Ticket::factory()->create(['department_id' => $this->dept->id, 'category_id' => $category->id, 'status' => $status->value]);

    $this->actingAs($this->admin)->delete(route('admin.ticketCategories.archive', $category->id))
        ->assertSessionHas('error', 'Cannot archive this category while any of its tickets is not yet closed or cancelled.');
    expect($category->fresh()->trashed())->toBeFalse();
})->with([
    'resolved' => [TicketStatus::RESOLVED],
    'endorsed' => [TicketStatus::ENDORSED],
]);

test('a category whose tickets are all finished can be archived', function () {
    $category = TicketCategory::factory()->create();
    foreach ([TicketStatus::CLOSED, TicketStatus::CANCELLED] as $status) {
        Ticket::factory()->create(['department_id' => $this->dept->id, 'category_id' => $category->id, 'status' => $status->value]);
    }

    $this->actingAs($this->admin)->delete(route('admin.ticketCategories.archive', $category->id))->assertSessionHas('success');
    expect($category->fresh()->trashed())->toBeTrue();
});

// --- M5: orphaned evidence cleanup ---

test('the evidence purge command deletes only files no row references, and only with --force', function () {
    Storage::fake(config('filesystems.default'));
    $disk = Storage::disk(config('filesystems.default'));
    $disk->put('accomplishments/signatures/kept.png', 'x');
    $disk->put('accomplishments/photos/kept.jpg', 'x');
    $disk->put('accomplishments/photos/orphan.jpg', 'x');
    $disk->put('accomplishments/signatures/orphan.png', 'x');

    $report = TicketAccomplishment::factory()->create(['signature_path' => 'accomplishments/signatures/kept.png']);
    AccomplishmentPhoto::create(['accomplishment_id' => $report->id, 'file_path' => 'accomplishments/photos/kept.jpg']);

    $this->artisan('storage:purge-orphaned-evidence')->expectsOutputToContain('orphaned evidence files: 2')->assertSuccessful();
    expect($disk->exists('accomplishments/photos/orphan.jpg'))->toBeTrue();

    $this->artisan('storage:purge-orphaned-evidence', ['--force' => true])->expectsOutputToContain('Deleted 2 orphaned files.')->assertSuccessful();
    expect($disk->exists('accomplishments/photos/orphan.jpg'))->toBeFalse()
        ->and($disk->exists('accomplishments/signatures/orphan.png'))->toBeFalse()
        ->and($disk->exists('accomplishments/photos/kept.jpg'))->toBeTrue()
        ->and($disk->exists('accomplishments/signatures/kept.png'))->toBeTrue();
});
