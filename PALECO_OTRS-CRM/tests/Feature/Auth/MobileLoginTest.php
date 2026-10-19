<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('supervisor can authenticate via mobile api and receives sanctum bearer token', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => 'mycka',
        'password' => 'Password',
        'device_name' => 'Flutter Test Device',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'access_token',
        'user' => [
            'id',
            'username',
            'role_slug',
            'department_id',
        ],
    ]);

    expect($response->json('user.username'))->toBe('mycka');
});

test('field personnel can authenticate via mobile api and receives teams roster', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => 'ralph',
        'password' => 'Password',
        'device_name' => 'Pixel 7 Mobile',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'access_token',
        'user' => [
            'id',
            'username',
            'role_slug',
        ],
    ]);

    expect($response->json('user.username'))->toBe('ralph');
});

test('web portal roles are rejected from mobile login api', function (string $username) {
    $response = $this->postJson('/api/v1/login', [
        'username' => $username,
        'password' => 'Password',
        'device_name' => 'Unauthorized Device',
    ]);

    $response->assertUnauthorized();
})->with(['allenglenn', 'alliah']);

test('mobile login fails with invalid password', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => 'mycka',
        'password' => 'WrongPassword',
        'device_name' => 'Flutter Test Device',
    ]);

    $response->assertUnauthorized();
});

test('deactivated mobile user is rejected with forbidden status', function () {
    $supervisor = User::where('username', 'mycka')->first();
    $supervisor->update(['is_active' => false]);

    $response = $this->postJson('/api/v1/login', [
        'username' => 'mycka',
        'password' => 'Password',
        'device_name' => 'Flutter Test Device',
    ]);

    $response->assertForbidden();
    $response->assertJsonFragment([
        'message' => 'User account is deactivated. Please contact the system administrator.',
    ]);
});

test('consecutive failed mobile logins trigger account lockout with status 423', function () {
    $user = User::where('username', 'mycka')->first();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/login', [
            'username' => 'mycka',
            'password' => 'WrongPassword',
            'device_name' => 'Test Phone',
        ]);
    }

    $user->refresh();
    expect($user->isLocked())->toBeTrue();

    $response = $this->postJson('/api/v1/login', [
        'username' => 'mycka',
        'password' => 'Password',
        'device_name' => 'Test Phone',
    ]);

    $response->assertStatus(423);
});

test('mobile login revokes previous tokens ensuring single-device session', function () {
    // First login
    $response1 = $this->postJson('/api/v1/login', [
        'username' => 'ralph',
        'password' => 'Password',
        'device_name' => 'Device 1',
    ]);
    $token1 = $response1->json('access_token');

    // Second login
    $response2 = $this->postJson('/api/v1/login', [
        'username' => 'ralph',
        'password' => 'Password',
        'device_name' => 'Device 2',
    ]);
    $token2 = $response2->json('access_token');

    expect($token1)->not->toBeNull()
        ->and($token2)->not->toBeNull()
        ->and($token1)->not->toBe($token2);

    // Old token must fail
    $this->withToken($token1)->getJson('/api/v1/user/profile')
        ->assertUnauthorized();

    // New token must succeed
    $this->withToken($token2)->getJson('/api/v1/user/profile')
        ->assertOk();
});

test('authenticated mobile user can fetch profile', function () {
    $user = User::where('username', 'mycka')->first();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/user/profile');

    $response->assertOk();
    $response->assertJsonPath('data.username', 'mycka');
});

test('authenticated mobile user can log out successfully', function () {
    $user = User::where('username', 'ralph')->first();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/logout');

    $response->assertOk();
    expect($user->tokens()->count())->toBe(0);
});

test('unauthenticated request to protected api endpoints returns 401', function () {
    $response = $this->getJson('/api/v1/user/profile');

    $response->assertUnauthorized();
});
