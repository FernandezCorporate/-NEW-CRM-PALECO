<?php

use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\User;
use App\Services\Users\ProfileService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['database.default' => 'profile_test', 'database.connections.profile_test' => [
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

    Schema::create('personal_access_tokens', function (Blueprint $table) {
        $table->id();
        $table->ulidMorphs('tokenable');
        $table->text('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable()->index();
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
        $table->timestamp('last_login')->nullable();
        $table->timestamp('locked_until')->nullable();
        $table->timestamps();
    });

    Schema::create('teams', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('team_name');
        $table->string('team_desc')->nullable();
        $table->time('shift_start')->nullable();
        $table->time('shift_end')->nullable();
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
});

test('profile service and endpoint hydrate assigned teams for field personnel', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $fieldRole = AccountRole::create(['id' => 1, 'role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);
    $leaderRole = TeamRole::create(['id' => 1, 'role_name' => 'Team Leader', 'slug_identifier' => 'team_leader']);

    $user = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'ralph_field',
        'first_name' => 'Ralph',
        'last_name' => 'Field',
        'contact' => '09123456789',
        'password' => Hash::make('password'),
        'role_id' => $fieldRole->id,
        'department_id' => $dept->id,
    ]);

    $team = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Alpha Emergency Response Crew',
        'team_desc' => 'High-voltage line crew',
        'shift_start' => '08:00',
        'shift_end' => '17:00',
        'department_id' => $dept->id,
    ]);

    DB::table('team_members')->insert([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'team_role_id' => $leaderRole->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 1. Verify ProfileService eager-loads teams
    $service = new ProfileService;
    $loadedUser = $service->getProfileData($user);
    expect($loadedUser->relationLoaded('teams'))->toBeTrue();
    expect($loadedUser->teams)->toHaveCount(1);

    // 2. Verify GET /api/user/profile response format
    $response = $this->actingAs($user)->getJson('/api/user/profile');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.username', 'ralph_field')
        ->assertJsonPath('data.role_slug', 'field_personnel')
        ->assertJsonPath('data.teams.0.id', $team->id)
        ->assertJsonPath('data.teams.0.team_name', 'Alpha Emergency Response Crew')
        ->assertJsonPath('data.teams.0.team_desc', 'High-voltage line crew')
        ->assertJsonPath('data.teams.0.shift_start', '08:00')
        ->assertJsonPath('data.teams.0.shift_end', '17:00')
        ->assertJsonPath('data.teams.0.team_role_id', 1)
        ->assertJsonPath('data.teams.0.role_name', 'Team Leader');
});

test('profile service and endpoint omit teams key for supervisor accounts', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $supRole = AccountRole::create(['id' => 2, 'role_name' => 'Department Supervisor', 'slug_identifier' => 'supervisor']);

    $supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'mycka_sup',
        'first_name' => 'Mycka',
        'last_name' => 'Supervisor',
        'contact' => '09123456780',
        'password' => Hash::make('password'),
        'role_id' => $supRole->id,
        'department_id' => $dept->id,
    ]);

    // 1. Verify ProfileService does not eager-load teams
    $service = new ProfileService;
    $loadedSupervisor = $service->getProfileData($supervisor);
    expect($loadedSupervisor->relationLoaded('teams'))->toBeFalse();

    // 2. Verify GET /api/user/profile response omits teams key
    $response = $this->actingAs($supervisor)->getJson('/api/user/profile');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.username', 'mycka_sup')
        ->assertJsonPath('data.role_slug', 'supervisor')
        ->assertJsonMissingPath('data.teams');
});

test('mobile login endpoint hydrates teams for field personnel and omits for supervisor', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $fieldRole = AccountRole::create(['id' => 1, 'role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);
    $supRole = AccountRole::create(['id' => 2, 'role_name' => 'Department Supervisor', 'slug_identifier' => 'supervisor']);
    $memberRole = TeamRole::create(['id' => 2, 'role_name' => 'Team Member', 'slug_identifier' => 'team_member']);

    $fieldUser = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'field_login_user',
        'first_name' => 'Field',
        'last_name' => 'Member',
        'contact' => '09123456781',
        'password' => Hash::make('secret123'),
        'role_id' => $fieldRole->id,
        'department_id' => $dept->id,
    ]);

    $supUser = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_login_user',
        'first_name' => 'Sup',
        'last_name' => 'Boss',
        'contact' => '09123456782',
        'password' => Hash::make('secret123'),
        'role_id' => $supRole->id,
        'department_id' => $dept->id,
    ]);

    $team = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Bravo Distribution Crew',
        'shift_start' => '09:00',
        'shift_end' => '18:00',
        'department_id' => $dept->id,
    ]);

    DB::table('team_members')->insert([
        'user_id' => $fieldUser->id,
        'team_id' => $team->id,
        'team_role_id' => $memberRole->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Field personnel login
    $fieldLogin = $this->postJson('/api/login', [
        'username' => 'field_login_user',
        'password' => 'secret123',
        'device_name' => 'Test Mobile Device',
    ]);

    $fieldLogin->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.role_slug', 'field_personnel')
        ->assertJsonPath('data.user.teams.0.team_name', 'Bravo Distribution Crew')
        ->assertJsonPath('data.user.teams.0.role_name', 'Team Member')
        ->assertJsonPath('user.teams.0.team_name', 'Bravo Distribution Crew');

    // Supervisor login
    $supLogin = $this->postJson('/api/login', [
        'username' => 'sup_login_user',
        'password' => 'secret123',
        'device_name' => 'Test Mobile Device',
    ]);

    $supLogin->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.role_slug', 'supervisor')
        ->assertJsonMissingPath('data.user.teams')
        ->assertJsonMissingPath('user.teams');
});

