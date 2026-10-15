<?php

use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['database.default' => 'phase6_test', 'database.connections.phase6_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'test-reverb-key',
            'secret' => 'test-reverb-secret',
            'app_id' => '123456',
            'options' => [
                'host' => '127.0.0.1',
                'port' => 8080,
                'scheme' => 'http',
                'useTLS' => false,
            ],
        ],
    ]);

    \Illuminate\Support\Facades\Broadcast::forgetDrivers();
    require base_path('routes/channels.php');

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

test('unauthenticated request to api broadcasting auth returns 401 unauthorized', function () {
    $response = $this->postJson('/api/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-team.01J00000000000000000000000',
    ]);

    $response->assertStatus(401);
});

test('field personnel can authenticate their assigned team private channel using sanctum bearer token', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $role = AccountRole::create(['id' => 1, 'role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);
    $teamRole = TeamRole::create(['id' => 1, 'role_name' => 'Lineman', 'slug_identifier' => 'lineman']);

    $user = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'lineman_juan',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'contact' => '09123456789',
        'password' => Hash::make('password'),
        'role_id' => $role->id,
        'department_id' => $dept->id,
    ]);

    $team = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Alpha Line Crew',
        'department_id' => $dept->id,
    ]);

    DB::table('team_members')->insert([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'team_role_id' => $teamRole->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $token = $user->createToken('mobile_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-team.'.$team->id,
        ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['auth']);
});

test('field personnel cannot authenticate a team private channel they do not belong to', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $role = AccountRole::create(['id' => 1, 'role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);

    $user = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'lineman_juan',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'contact' => '09123456789',
        'password' => Hash::make('password'),
        'role_id' => $role->id,
        'department_id' => $dept->id,
    ]);

    $otherTeam = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Bravo Line Crew',
        'department_id' => $dept->id,
    ]);

    $token = $user->createToken('mobile_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-team.'.$otherTeam->id,
        ]);

    $response->assertStatus(403);
});

test('supervisor can authenticate their own department private channel', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $role = AccountRole::create(['id' => 2, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);

    $supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_pedro',
        'first_name' => 'Pedro',
        'last_name' => 'Santos',
        'contact' => '09123456780',
        'password' => Hash::make('password'),
        'role_id' => $role->id,
        'department_id' => $dept->id,
    ]);

    $token = $supervisor->createToken('mobile_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-department.'.$dept->id,
        ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['auth']);
});

test('supervisor cannot authenticate a different department private channel', function () {
    $myDept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $otherDept = Department::create(['id' => 2, 'dept_name' => 'Customer Accounts']);
    $role = AccountRole::create(['id' => 2, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);

    $supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_pedro',
        'first_name' => 'Pedro',
        'last_name' => 'Santos',
        'contact' => '09123456780',
        'password' => Hash::make('password'),
        'role_id' => $role->id,
        'department_id' => $myDept->id,
    ]);

    $token = $supervisor->createToken('mobile_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-department.'.$otherDept->id,
        ]);

    $response->assertStatus(403);
});

test('supervisor can authenticate team channels within their own department', function () {
    $myDept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $role = AccountRole::create(['id' => 2, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);

    $supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_pedro',
        'first_name' => 'Pedro',
        'last_name' => 'Santos',
        'contact' => '09123456780',
        'password' => Hash::make('password'),
        'role_id' => $role->id,
        'department_id' => $myDept->id,
    ]);

    $team = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Alpha Line Crew',
        'department_id' => $myDept->id,
    ]);

    $token = $supervisor->createToken('mobile_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-team.'.$team->id,
        ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['auth']);
});

test('supervisor cannot authenticate team channels of a different department', function () {
    $myDept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $otherDept = Department::create(['id' => 2, 'dept_name' => 'Customer Accounts']);
    $role = AccountRole::create(['id' => 2, 'role_name' => 'Supervisor', 'slug_identifier' => 'supervisor']);

    $supervisor = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'sup_pedro',
        'first_name' => 'Pedro',
        'last_name' => 'Santos',
        'contact' => '09123456780',
        'password' => Hash::make('password'),
        'role_id' => $role->id,
        'department_id' => $myDept->id,
    ]);

    $otherTeam = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Commercial Inspection Team',
        'department_id' => $otherDept->id,
    ]);

    $token = $supervisor->createToken('mobile_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-team.'.$otherTeam->id,
        ]);

    $response->assertStatus(403);
});

test('admin can authenticate both team and department channels regardless of department', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $adminRole = AccountRole::create(['id' => 3, 'role_name' => 'Admin', 'slug_identifier' => 'admin']);

    $admin = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'admin_user',
        'first_name' => 'System',
        'last_name' => 'Admin',
        'contact' => '09123456781',
        'password' => Hash::make('password'),
        'role_id' => $adminRole->id,
        'department_id' => null,
    ]);

    $team = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Alpha Line Crew',
        'department_id' => $dept->id,
    ]);

    $token = $admin->createToken('mobile_token')->plainTextToken;

    $teamResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-team.'.$team->id,
        ]);

    $teamResponse->assertStatus(200);

    $deptResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-department.'.$dept->id,
        ]);

    $deptResponse->assertStatus(200);
});

test('cwd officer can authenticate team, department, and cwd.operations channels', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $cwdRole = AccountRole::create(['id' => 4, 'role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer']);

    $cwdOfficer = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'cwd_officer_user',
        'first_name' => 'CWD',
        'last_name' => 'Officer',
        'contact' => '09123456782',
        'password' => Hash::make('password'),
        'role_id' => $cwdRole->id,
        'department_id' => null,
    ]);

    $team = Team::create([
        'id' => (string) Str::ulid(),
        'team_name' => 'Alpha Line Crew',
        'department_id' => $dept->id,
    ]);

    $token = $cwdOfficer->createToken('mobile_token')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-cwd.operations',
        ])
        ->assertStatus(200);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-team.'.$team->id,
        ])
        ->assertStatus(200);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-department.'.$dept->id,
        ])
        ->assertStatus(200);
});

test('web user with active session can authenticate broadcasting auth route', function () {
    $dept = Department::create(['id' => 1, 'dept_name' => 'Technical Operations']);
    $adminRole = AccountRole::create(['id' => 3, 'role_name' => 'Admin', 'slug_identifier' => 'admin']);

    $admin = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'admin_web',
        'first_name' => 'Admin',
        'last_name' => 'Web',
        'contact' => '09123456783',
        'password' => Hash::make('password'),
        'role_id' => $adminRole->id,
        'department_id' => null,
    ]);

    $response = $this->actingAs($admin, 'web')
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-cwd.operations',
        ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['auth']);
});

