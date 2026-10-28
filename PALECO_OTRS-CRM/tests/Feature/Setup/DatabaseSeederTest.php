<?php

use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('seeding a fresh system creates the roles and exactly one admin account', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1)
        ->and(AccountRole::pluck('slug_identifier')->sort()->values()->all())->toBe(['admin', 'cwd_officer', 'field_personnel', 'supervisor'])
        ->and(TeamRole::count())->toBe(3)
        ->and(Department::count())->toBe(0)
        ->and(Team::count())->toBe(0)
        ->and(TicketCategory::count())->toBe(0);

    $admin = User::sole();
    expect($admin->username)->toBe('PALECO_ADMIN_1')
        ->and($admin->role->slug_identifier)->toBe('admin')
        ->and($admin->department_id)->toBeNull()
        ->and((bool) $admin->is_active)->toBeTrue()
        ->and(Hash::check('OTRS-Testing', $admin->password))->toBeTrue();
});

test('running the seeder again adds nothing and keeps a password changed after setup', function () {
    $this->seed(DatabaseSeeder::class);
    User::sole()->update(['password' => 'Changed-Password1']);

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1)
        ->and(AccountRole::count())->toBe(4)
        ->and(TeamRole::count())->toBe(3)
        ->and(Hash::check('Changed-Password1', User::sole()->password))->toBeTrue();
});

test('the seeded admin can sign in to the admin portal', function () {
    $this->seed(DatabaseSeeder::class);

    $this->post(route('attemptLogin', ['role' => 'admin']), [
        'username' => 'PALECO_ADMIN_1',
        'password' => 'OTRS-Testing',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs(User::sole());
});
