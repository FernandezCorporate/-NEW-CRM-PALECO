<?php

use App\Models\AccountRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['database.default' => 'auth_hardening_test', 'database.connections.auth_hardening_test' => [
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

    RateLimiter::clear('auth:ip:127.0.0.1');
    RateLimiter::clear('auth:account:attempts:target_user');
    RateLimiter::clear('auth:account:attempts:field_worker_ralph');
    RateLimiter::clear('auth:account:attempts:admin_super');
    RateLimiter::clear('auth:account:attempts:clean_rate_user');
    RateLimiter::clear('auth:account:attempts:proxy_victim');
});

test('web login failure displays accurate 5-attempt countdown and triggers lockout on 5th attempt', function () {
    $adminRole = AccountRole::create(['id' => 1, 'role_name' => 'Admin', 'slug_identifier' => 'admin']);
    $user = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'target_user',
        'first_name' => 'Target',
        'last_name' => 'Admin',
        'contact' => '09123456789',
        'password' => Hash::make('correct_password'),
        'role_id' => $adminRole->id,
    ]);

    // Attempt 1: 4 remaining
    $res1 = $this->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'target_user', 'password' => 'wrong1']);
    $res1->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 4 attempt(s) remaining.']);

    // Attempt 2: 3 remaining
    $res2 = $this->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'target_user', 'password' => 'wrong2']);
    $res2->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 3 attempt(s) remaining.']);

    // Attempt 3: 2 remaining
    $res3 = $this->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'target_user', 'password' => 'wrong3']);
    $res3->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 2 attempt(s) remaining.']);

    // Attempt 4: 1 remaining
    $res4 = $this->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'target_user', 'password' => 'wrong4']);
    $res4->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 1 attempt(s) remaining.']);

    // Attempt 5: Account lockout triggered
    $res5 = $this->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'target_user', 'password' => 'wrong5']);
    $user->refresh();
    expect($user->locked_until)->not->toBeNull();
    $res5->assertSessionHasErrors('error');
    $errorMessage = session('errors')->first('error');
    expect($errorMessage)->toContain('temporarily locked due to multiple failed attempts');

    // Attempt 6: Still locked
    $res6 = $this->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'target_user', 'password' => 'correct_password']);
    $res6->assertSessionHasErrors('error');
    $lockMessage = session('errors')->first('error');
    expect($lockMessage)->toContain('This account is temporarily locked. Please wait');
});

test('web login eliminates role enumeration oracle when valid user accesses mismatched portal', function () {
    $fieldRole = AccountRole::create(['id' => 2, 'role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel']);
    $user = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'field_worker_ralph',
        'first_name' => 'Ralph',
        'last_name' => 'Crew',
        'contact' => '09123456789',
        'password' => Hash::make('valid_password_123'),
        'role_id' => $fieldRole->id,
    ]);

    // Attempt admin portal login with valid password of field worker
    $response = $this->post(route('attemptLogin', ['role' => 'admin']), [
        'username' => 'field_worker_ralph',
        'password' => 'valid_password_123',
    ]);

    // Must NOT reveal portal mismatch or existence
    $response->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 4 attempt(s) remaining.']);
    $this->assertGuest();
});

test('mobile login eliminates role enumeration oracle and returns 401 with generic error for web accounts', function () {
    $adminRole = AccountRole::create(['id' => 1, 'role_name' => 'Admin', 'slug_identifier' => 'admin']);
    $admin = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'admin_super',
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'contact' => '09123456789',
        'password' => Hash::make('valid_admin_pass'),
        'role_id' => $adminRole->id,
    ]);

    // Admin attempts to login to mobile API
    $response = $this->postJson('/api/login', [
        'username' => 'admin_super',
        'password' => 'valid_admin_pass',
        'device_name' => 'Android Device',
    ]);

    // Must return 401 Unauthorized with generic credential error, NOT 403 Forbidden
    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The provided credentials do not match our records. You have 4 attempt(s) remaining.');
});

test('successful authentication clears account failed attempts', function () {
    $adminRole = AccountRole::create(['id' => 1, 'role_name' => 'Admin', 'slug_identifier' => 'admin']);
    $user = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'clean_rate_user',
        'first_name' => 'Clean',
        'last_name' => 'User',
        'contact' => '09123456789',
        'password' => Hash::make('correct_pass_123'),
        'role_id' => $adminRole->id,
    ]);

    // 1 failed attempt
    $this->post(route('attemptLogin', ['role' => 'admin']), [
        'username' => 'clean_rate_user',
        'password' => 'wrong',
    ]);
    expect(RateLimiter::attempts('auth:account:attempts:clean_rate_user'))->toBe(1);

    // Successful attempt
    $res = $this->post(route('attemptLogin', ['role' => 'admin']), [
        'username' => 'clean_rate_user',
        'password' => 'correct_pass_123',
    ]);
    $res->assertRedirect(route('admin.dashboard'));
    expect(RateLimiter::attempts('auth:account:attempts:clean_rate_user'))->toBe(0);
});

test('layer 1 ip velocity limiter blocks spam attacks independent of account state', function () {
    $adminRole = AccountRole::create(['id' => 1, 'role_name' => 'Admin', 'slug_identifier' => 'admin']);
    User::create([
        'id' => (string) Str::ulid(),
        'username' => 'ip_test_user',
        'first_name' => 'IP',
        'last_name' => 'Tester',
        'contact' => '09123456789',
        'password' => Hash::make('pass123'),
        'role_id' => $adminRole->id,
    ]);

    // Perform 10 rapid requests from same IP (allowed by velocity limit)
    for ($i = 1; $i <= 10; $i++) {
        $this->post(route('attemptLogin', ['role' => 'admin']), [
            'username' => 'random_user_'.$i,
            'password' => 'wrong',
        ]);
    }

    // 11th request from same IP is throttled
    $blockedResponse = $this->post(route('attemptLogin', ['role' => 'admin']), [
        'username' => 'ip_test_user',
        'password' => 'wrong',
    ]);

    $blockedResponse->assertSessionHasErrors('error');
    $errorMessage = session('errors')->first('error');
    expect($errorMessage)->toContain('Too many attempts from this network. Please wait');
});

test('layer 2 account lockout defeats proxy hopping across different ip addresses', function () {
    $adminRole = AccountRole::create(['id' => 1, 'role_name' => 'Admin', 'slug_identifier' => 'admin']);
    $victim = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'proxy_victim',
        'first_name' => 'Victim',
        'last_name' => 'User',
        'contact' => '09123456789',
        'password' => Hash::make('secret_password'),
        'role_id' => $adminRole->id,
    ]);

    // Attacker rotates through 5 different proxy IPs targeting the same username
    // Attempt 1 from IP A: 4 remaining
    $r1 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.101'])
        ->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'proxy_victim', 'password' => 'bad1']);
    $r1->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 4 attempt(s) remaining.']);

    // Attempt 2 from IP B: 3 remaining
    $r2 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.102'])
        ->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'proxy_victim', 'password' => 'bad2']);
    $r2->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 3 attempt(s) remaining.']);

    // Attempt 3 from IP C: 2 remaining
    $r3 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.103'])
        ->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'proxy_victim', 'password' => 'bad3']);
    $r3->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 2 attempt(s) remaining.']);

    // Attempt 4 from IP D: 1 remaining
    $r4 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.104'])
        ->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'proxy_victim', 'password' => 'bad4']);
    $r4->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 1 attempt(s) remaining.']);

    // Attempt 5 from IP E: Account lockout triggered despite new IP!
    $r5 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.105'])
        ->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'proxy_victim', 'password' => 'bad5']);

    $victim->refresh();
    expect($victim->locked_until)->not->toBeNull();
    $r5->assertSessionHasErrors('error');
    expect(session('errors')->first('error'))->toContain('temporarily locked due to multiple failed attempts');
});

test('non-existent gibberish username undergoes virtual cache lockout preventing username enumeration', function () {
    RateLimiter::clear('auth:account:attempts:gibberish_fake_user');
    RateLimiter::clear('auth:account:locked:gibberish_fake_user');

    // Attempt 1: 4 remaining
    $r1 = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'gibberish_fake_user', 'password' => 'wrong1']);
    $r1->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 4 attempt(s) remaining.']);

    // Attempt 2: 3 remaining
    $r2 = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'gibberish_fake_user', 'password' => 'wrong2']);
    $r2->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 3 attempt(s) remaining.']);

    // Attempt 3: 2 remaining
    $r3 = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])
        ->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'gibberish_fake_user', 'password' => 'wrong3']);
    $r3->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 2 attempt(s) remaining.']);

    // Attempt 4: 1 remaining
    $r4 = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])
        ->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'gibberish_fake_user', 'password' => 'wrong4']);
    $r4->assertSessionHasErrors(['error' => 'The provided credentials do not match our records. You have 1 attempt(s) remaining.']);

    // Attempt 5: Enacts virtual 15-minute cache lockout
    $r5 = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'gibberish_fake_user', 'password' => 'wrong5']);
    $r5->assertSessionHasErrors('error');
    expect(session('errors')->first('error'))->toContain('temporarily locked due to multiple failed attempts');

    // Attempt 6: MUST REMAIN LOCKED (proves fix for the oversight where fake usernames reset to 4 attempts)
    $r6 = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.6'])
        ->post(route('attemptLogin', ['role' => 'admin']), ['username' => 'gibberish_fake_user', 'password' => 'wrong6']);
    $r6->assertSessionHasErrors('error');
    expect(session('errors')->first('error'))->toContain('This account is temporarily locked. Please wait');
});

