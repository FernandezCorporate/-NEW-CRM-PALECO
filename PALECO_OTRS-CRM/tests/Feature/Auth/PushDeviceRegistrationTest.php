<?php

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
    $this->admin = User::where('username', 'allenglenn')->first();
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->personnel = User::where('username', 'ralph')->first();
    $this->fcmToken = 'fcm-token-ralph-phone-0123456789abcdef0123456789';
});

// The test client remembers the last signed-in user between requests; a real phone starts fresh each time
function freshPushRequest($test)
{
    app('auth')->forgetGuards();

    return $test;
}

// Registers a phone the way the app does after login: a real Sanctum login token in the Bearer header
function registerPushPhone($test, string $bearer, ?string $fcmToken = null)
{
    return freshPushRequest($test)->withToken($bearer)->postJson('/api/v1/devices/push-token', ['fcm_token' => $fcmToken ?? $test->fcmToken]);
}

test('a field crew member registers their phone for the current login', function () {
    $login = $this->personnel->createToken('Pixel 7');

    registerPushPhone($this, $login->plainTextToken)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.platform', 'android');

    $device = PushDevice::first();
    expect($device->user_id)->toBe($this->personnel->id)
        ->and($device->personal_access_token_id)->toBe($login->accessToken->id)
        ->and($device->fcm_token)->toBe($this->fcmToken);
});

test('a supervisor can register a phone too', function () {
    registerPushPhone($this, $this->supervisor->createToken('Galaxy A15')->plainTextToken)->assertCreated();

    expect(PushDevice::where('user_id', $this->supervisor->id)->count())->toBe(1);
});

test('sending the same token again keeps one row and refreshes last seen', function () {
    $bearer = $this->personnel->createToken('Pixel 7')->plainTextToken;
    registerPushPhone($this, $bearer)->assertCreated();
    $firstSeen = PushDevice::first()->last_seen_at;
    $this->travel(5)->minutes();

    registerPushPhone($this, $bearer)->assertOk();

    expect(PushDevice::count())->toBe(1)
        ->and(PushDevice::first()->last_seen_at->isAfter($firstSeen))->toBeTrue();
});

test('a refreshed firebase token replaces the old one for the same login', function () {
    $bearer = $this->personnel->createToken('Pixel 7')->plainTextToken;
    registerPushPhone($this, $bearer)->assertCreated();

    registerPushPhone($this, $bearer, 'fcm-token-ralph-phone-REFRESHED-0123456789')->assertOk();

    expect(PushDevice::count())->toBe(1)
        ->and(PushDevice::first()->fcm_token)->toBe('fcm-token-ralph-phone-REFRESHED-0123456789');
});

test('a shared phone moves to the account that signed in last', function () {
    registerPushPhone($this, $this->personnel->createToken('Shared phone')->plainTextToken)->assertCreated();

    registerPushPhone($this, $this->supervisor->createToken('Shared phone')->plainTextToken)->assertCreated();

    expect(PushDevice::count())->toBe(1)
        ->and(PushDevice::first()->user_id)->toBe($this->supervisor->id);
});

test('logging out removes the phone', function () {
    $bearer = $this->personnel->createToken('Pixel 7')->plainTextToken;
    registerPushPhone($this, $bearer)->assertCreated();

    freshPushRequest($this)->withToken($bearer)->postJson('/api/v1/logout')->assertOk();

    expect(PushDevice::count())->toBe(0);
});

test('signing in on another phone removes the previous phone', function () {
    registerPushPhone($this, $this->personnel->createToken('Old phone')->plainTextToken)->assertCreated();

    freshPushRequest($this)->withHeaders(['Authorization' => ''])
        ->postJson('/api/v1/login', ['username' => 'ralph', 'password' => 'Password', 'device_name' => 'New phone'])
        ->assertOk();

    expect(PushDevice::count())->toBe(0);
});

test('deactivating the account removes its phone', function () {
    // A second active crew member, so the rule "at least one active account per role" allows the deactivation
    User::factory()->fieldPersonnel()->create();
    registerPushPhone($this, $this->personnel->createToken('Pixel 7')->plainTextToken)->assertCreated();

    $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $this->personnel));

    expect($this->personnel->fresh()->is_active)->toBeFalse()
        ->and(PushDevice::count())->toBe(0);
});

test('the app can stop notifications for its phone', function () {
    $bearer = $this->personnel->createToken('Pixel 7')->plainTextToken;
    registerPushPhone($this, $bearer)->assertCreated();

    freshPushRequest($this)->withToken($bearer)->deleteJson('/api/v1/devices/push-token')
        ->assertOk()
        ->assertJsonPath('message', 'This device will no longer receive notifications.');

    expect(PushDevice::count())->toBe(0);
});

test('web accounts cannot register a phone', function (string $username) {
    $user = User::where('username', $username)->first();

    registerPushPhone($this, $user->createToken('Browser')->plainTextToken)
        ->assertForbidden()
        ->assertJsonPath('message', 'Only mobile app accounts can receive push notifications.');

    expect(PushDevice::count())->toBe(0);
})->with(['allenglenn', 'alliah']);

test('a temporary token without a login record is refused', function () {
    Sanctum::actingAs($this->personnel, ['*']);

    $this->postJson('/api/v1/devices/push-token', ['fcm_token' => $this->fcmToken])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'A mobile login token is required to register this device.');

    expect(PushDevice::count())->toBe(0);
});

test('the token is validated', function () {
    $bearer = $this->personnel->createToken('Pixel 7')->plainTextToken;

    $this->withToken($bearer)->postJson('/api/v1/devices/push-token', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.fcm_token.0', 'The device push token is required.');
});

test('registering requires a login', function () {
    $this->postJson('/api/v1/devices/push-token', ['fcm_token' => $this->fcmToken])->assertUnauthorized();
});
