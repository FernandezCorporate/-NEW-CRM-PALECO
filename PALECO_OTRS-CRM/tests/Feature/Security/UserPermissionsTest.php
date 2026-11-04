<?php

use App\Actions\Users\UpdateUserPermissions;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
    $this->admin = User::where('username', 'allenglenn')->first();
    $this->cwdOfficer = User::where('username', 'alliah')->first();
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->personnel = User::where('username', 'ralph')->first();
    $this->dept = Department::find($this->supervisor->department_id) ?? Department::first();
    $this->team = Team::first();
    $this->category = TicketCategory::first();
});

afterEach(function () {
    Carbon::setTestNow();
});

// Removes permissions from an account the way the user form does
function revoke(User $user, array $names, User $actor): void
{
    $keep = array_values(array_diff(PermissionCatalog::forRole($user->role->slug_identifier), $names));
    app(UpdateUserPermissions::class)->execute($user, $keep, $actor);
    $user->unsetRelation('permissions');
}

function userPayload(array $overrides = []): array
{
    return array_merge([
        'username' => 'newofficer',
        'first_name' => 'New',
        'last_name' => 'Officer',
        'contact' => '09171234567',
        'role_id' => 2,
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ], $overrides);
}

test('every account starts with exactly the default permissions of its role', function (string $username) {
    $user = User::where('username', $username)->first();

    expect($user->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(PermissionCatalog::forRole($user->role->slug_identifier))->sort()->values()->all())
        ->and(PermissionCatalog::isCustomized($user))->toBeFalse();
})->with(['allenglenn', 'alliah', 'mycka', 'ralph']);

test('an account created through a factory receives its role defaults', function () {
    $officer = User::factory()->cwdOfficer()->create();

    expect($officer->hasPermission('tickets.create'))->toBeTrue()
        ->and($officer->hasPermission('users.view'))->toBeFalse();
});

test('an admin without a module permission cannot open it and does not see its sidebar link', function () {
    revoke($this->admin, ['departments.view'], $this->admin);

    $this->actingAs($this->admin)->get(route('admin.departments'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.departments'), false)
        ->assertSee(route('admin.teams'), false);
});

test('an admin without create permission does not see the create button and cannot create', function () {
    revoke($this->admin, ['departments.create'], $this->admin);

    $this->actingAs($this->admin)->get(route('admin.departments'))
        ->assertOk()
        ->assertDontSee(route('admin.departments.createForm'), false);
    $this->actingAs($this->admin)->post(route('admin.departments.store'), ['dept_name' => 'Billing Department'])->assertForbidden();
    expect(Department::where('dept_name', 'Billing Department')->exists())->toBeFalse();
});

test('a cwd officer without cancel permission cannot cancel a ticket and the button is hidden', function () {
    $this->cwdOfficer->update(['is_active' => true]);
    revoke($this->cwdOfficer, ['tickets.cancel'], $this->admin);
    $ticket = Ticket::factory()->open()->create(['department_id' => $this->dept->id, 'created_by_id' => $this->cwdOfficer->id]);

    $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.cancel', $ticket), ['cancellation_reason' => 'Duplicate complaint'])
        ->assertForbidden();
    $this->actingAs($this->cwdOfficer)->get(route('cwd.tickets.show', $ticket))
        ->assertOk()
        ->assertDontSee(route('cwd.tickets.cancelConfirm', $ticket), false);
    expect($ticket->fresh()->status)->toBe(TicketStatus::OPEN);
});

test('a view-only cwd officer can still read tickets but not create them', function () {
    revoke($this->cwdOfficer, ['tickets.create', 'tickets.update', 'tickets.cancel', 'tickets.force_close', 'tickets.remark', 'endorsements.decide'], $this->admin);

    $this->actingAs($this->cwdOfficer)->get(route('cwd.tickets'))->assertOk()->assertDontSee(route('cwd.tickets.createForm'), false);
    $this->actingAs($this->cwdOfficer)->get(route('cwd.tickets.createForm'))->assertForbidden();
});

test('an admin can create a cwd officer with a reduced checklist', function () {
    $keep = ['consumers.view', 'tickets.view'];

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), userPayload(['permissions_submitted' => 1, 'permissions' => $keep]))
        ->assertRedirect(route('admin.users'));

    $created = User::where('username', 'newofficer')->first();
    expect($created->permissions->pluck('name')->sort()->values()->all())->toBe($keep)
        ->and(PermissionCatalog::isCustomized($created))->toBeTrue();
});

test('a permission that does not belong to the role is refused', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), userPayload(['permissions_submitted' => 1, 'permissions' => ['tickets.view', 'users.create']]))
        ->assertSessionHasErrors('permissions');

    expect(User::where('username', 'newofficer')->exists())->toBeFalse();
});

test('an admin cannot give a new administrator a permission they do not hold', function () {
    revoke($this->admin, ['audit.view'], $this->admin);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), userPayload([
            'username' => 'newadmin', 'role_id' => 1,
            'permissions_submitted' => 1, 'permissions' => ['users.view', 'audit.view'],
        ]))
        ->assertSessionHasErrors('permissions');

    expect(User::where('username', 'newadmin')->exists())->toBeFalse();
});

test('an admin can create an auditor administrator with only the audit permission', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), userPayload([
            'username' => 'auditor', 'role_id' => 1,
            'permissions_submitted' => 1, 'permissions' => ['audit.view'],
        ]))
        ->assertRedirect(route('admin.users'));

    $auditor = User::where('username', 'auditor')->first();
    $this->actingAs($auditor)->get(route('admin.monitoring.index'))->assertOk();
    $this->actingAs($auditor)->get(route('admin.users'))->assertForbidden();
});

test('an admin can change a cwd officer permissions from the edit form and the change is logged', function () {
    $keep = array_values(array_diff(PermissionCatalog::forRole('cwd_officer'), ['tickets.force_close']));

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $this->cwdOfficer), [
            'original_updated_at' => (string) $this->cwdOfficer->updated_at,
            'username' => 'alliah', 'first_name' => 'alliah', 'last_name' => 'officer', 'contact' => '09123456789',
            'permissions_submitted' => 1, 'permissions' => $keep,
        ])
        ->assertSessionHas('success');

    $officer = $this->cwdOfficer->fresh();
    expect($officer->hasPermission('tickets.force_close'))->toBeFalse()
        ->and($officer->permissions_updated_at)->not->toBeNull()
        ->and(Activity::where('event', 'permissions_updated')->where('subject_id', $officer->id)->exists())->toBeTrue();
});

test('administrator permissions cannot be changed by themselves or by another administrator', function () {
    $otherAdmin = User::factory()->admin()->create();

    expect($this->admin->can('managePermissions', [User::class, $this->admin]))->toBeFalse()
        ->and($this->admin->can('managePermissions', [User::class, $otherAdmin]))->toBeFalse()
        ->and($this->admin->can('managePermissions', [User::class, $this->cwdOfficer]))->toBeTrue();

    $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
        'original_updated_at' => (string) $this->admin->updated_at,
        'username' => 'allenglenn', 'first_name' => 'allen glenn', 'last_name' => 'fernandez', 'contact' => '09123456789',
        'permissions_submitted' => 1, 'permissions' => ['users.view'],
    ]);

    expect($this->admin->fresh()->permissions)->toHaveCount(count(PermissionCatalog::forRole('admin')));
});

test('without the permissions permission the checklist is hidden and defaults are kept', function () {
    revoke($this->admin, ['users.permissions'], $this->admin);

    $this->actingAs($this->admin)->get(route('admin.users.createForm'))->assertOk()->assertDontSee('permission-checklist', false);
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), userPayload(['permissions_submitted' => 1, 'permissions' => ['tickets.view']]))
        ->assertRedirect(route('admin.users'));

    expect(User::where('username', 'newofficer')->first()->permissions)->toHaveCount(count(PermissionCatalog::forRole('cwd_officer')));
});

test('the last supervisor able to assign cannot lose it while the department has unfinished tickets', function () {
    Ticket::factory()->open()->create(['department_id' => $this->supervisor->department_id, 'created_by_id' => $this->cwdOfficer->id]);

    expect(fn () => revoke($this->supervisor, ['tickets.assign'], $this->admin))->toThrow(ValidationException::class);
    expect($this->supervisor->fresh()->hasPermission('tickets.assign'))->toBeTrue();

    // A second supervisor who can assign makes the change safe
    User::factory()->supervisor($this->supervisor->department_id)->create();
    revoke($this->supervisor, ['tickets.assign'], $this->admin);
    expect($this->supervisor->fresh()->hasPermission('tickets.assign'))->toBeFalse();
});

test('the only crew member able to start cannot lose it while the team has assigned tickets', function () {
    Ticket::factory()->assigned($this->team)->create(['department_id' => $this->team->department_id, 'created_by_id' => $this->cwdOfficer->id]);

    expect(fn () => revoke($this->personnel, ['tickets.start'], $this->admin))->toThrow(ValidationException::class);
    expect($this->personnel->fresh()->hasPermission('tickets.start'))->toBeTrue();
});

test('a crew member without start permission is refused online but offline work on an earlier assignment is accepted', function () {
    Carbon::setTestNow('2026-11-04 08:00:00');
    $earlier = Ticket::factory()->assigned($this->team)->create(['department_id' => $this->team->department_id, 'created_by_id' => $this->cwdOfficer->id]);
    TicketAssignment::create(['ticket_id' => $earlier->id, 'team_id' => $this->team->id, 'assigned_by_id' => $this->supervisor->id]);

    // The permission is removed after the assignment; the crew had no other tickets, so no guard applies yet
    Carbon::setTestNow('2026-11-04 09:00:00');
    $earlier->update(['status' => TicketStatus::OPEN]);
    revoke($this->personnel, ['tickets.start'], $this->admin);
    $earlier->update(['status' => TicketStatus::ASSIGNED]);

    Carbon::setTestNow('2026-11-04 10:00:00');
    $later = Ticket::factory()->assigned($this->team)->create(['department_id' => $this->team->department_id, 'created_by_id' => $this->cwdOfficer->id]);
    TicketAssignment::create(['ticket_id' => $later->id, 'team_id' => $this->team->id, 'assigned_by_id' => $this->supervisor->id]);

    Sanctum::actingAs($this->personnel->fresh(), ['*']);

    $this->withHeaders(['X-Idempotency-Key' => 'online-start-after-revoke'])
        ->patchJson("/api/v1/tickets/{$earlier->id}/start")
        ->assertForbidden()
        ->assertJsonPath('message', 'You do not have permission to start tickets.');

    $this->withHeaders(['X-Idempotency-Key' => 'offline-start-earlier', 'X-Client-Timestamp' => '2026-11-04T08:30:00+08:00'])
        ->patchJson("/api/v1/tickets/{$earlier->id}/start")
        ->assertOk();

    $this->withHeaders(['X-Idempotency-Key' => 'offline-start-later', 'X-Client-Timestamp' => '2026-11-04T10:05:00+08:00'])
        ->patchJson("/api/v1/tickets/{$later->id}/start")
        ->assertForbidden();
});

test('a supervisor without endorse permission gets a 403 with a clear message', function () {
    revoke($this->supervisor, ['tickets.endorse'], $this->admin);
    $ticket = Ticket::factory()->open()->create(['department_id' => $this->supervisor->department_id, 'created_by_id' => $this->cwdOfficer->id]);
    Sanctum::actingAs($this->supervisor->fresh(), ['*']);

    $this->postJson("/api/v1/tickets/{$ticket->id}/endorse", [
        'suggested_department_id' => Department::where('id', '!=', $ticket->department_id)->first()->id,
        'reason' => 'Needs another department.',
    ])->assertForbidden()->assertJsonPath('message', 'You do not have permission to endorse tickets.');
});

test('the mobile profile lists the account permissions so the app can hide actions', function () {
    Sanctum::actingAs($this->personnel, ['*']);

    $this->getJson('/api/v1/user/profile')
        ->assertOk()
        ->assertJsonPath('data.permissions', PermissionCatalog::forRole('field_personnel'));
});

test('the last supervisor able to assign and verify cannot be deactivated while the department has unfinished tickets', function () {
    $other = User::factory()->supervisor($this->supervisor->department_id)->create();
    Ticket::factory()->open()->create(['department_id' => $this->supervisor->department_id, 'created_by_id' => $this->cwdOfficer->id]);
    revoke($other, ['tickets.assign'], $this->admin);

    $this->actingAs($this->admin)
        ->patch(route('admin.users.deactivate', $this->supervisor))
        ->assertSessionHasErrors();

    expect($this->supervisor->fresh()->is_active)->toBeTrue();
});
