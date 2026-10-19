<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('admin can administratively unlock a locked user account and clear rate limiter keys', function () {
    $admin = User::where('username', 'allenglenn')->first();
    $targetUser = User::where('username', 'alliah')->first();

    // 1. Simulate account lockout
    $targetUser->updateQuietly(['locked_until' => now()->addMinutes(15)]);
    $normalizedUsername = strtolower($targetUser->username);
    RateLimiter::hit('auth:account:locked:'.$normalizedUsername, 900);
    RateLimiter::hit('auth:account:attempts:'.$normalizedUsername, 900);

    expect($targetUser->fresh()->isLocked())->toBeTrue();
    expect($targetUser->fresh()->lockoutMinutesRemaining())->toBeGreaterThan(0);
    expect(RateLimiter::tooManyAttempts('auth:account:locked:'.$normalizedUsername, 1))->toBeTrue();

    // 2. Admin triggers unlock
    $response = $this->actingAs($admin)->patch(route('admin.users.unlock', ['user' => $targetUser]));

    $response->assertRedirect(route('admin.users'));
    $response->assertSessionHas('success', "Security lockout for user {$targetUser->username} has been cleared.");

    // 3. Verify user is unlocked
    $freshUser = $targetUser->fresh();
    expect($freshUser->locked_until)->toBeNull();
    expect($freshUser->isLocked())->toBeFalse();
    expect($freshUser->lockoutMinutesRemaining())->toBe(0);

    // 4. Verify rate limiter cache keys were purged
    expect(RateLimiter::tooManyAttempts('auth:account:locked:'.$normalizedUsername, 1))->toBeFalse();
    expect(RateLimiter::attempts('auth:account:attempts:'.$normalizedUsername))->toBe(0);

    // 5. Verify audit log entry
    $activity = Activity::where('log_name', 'Users')
        ->where('event', 'account_unlocked')
        ->where('subject_id', $targetUser->id)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($admin->id);
    expect($activity->properties['action'])->toBe('admin_lockout_bypass');
    expect($activity->properties['username'])->toBe($targetUser->username);
});

test('admin unlocking from details page redirects back to user details page', function () {
    $admin = User::where('username', 'allenglenn')->first();
    $targetUser = User::where('username', 'alliah')->first();

    $targetUser->updateQuietly(['locked_until' => now()->addMinutes(15)]);

    $response = $this->actingAs($admin)->patch(route('admin.users.unlock', ['user' => $targetUser]), [
        'source' => 'details',
    ]);

    $response->assertRedirect(route('admin.users.show', ['user' => $targetUser]));
    $response->assertSessionHas('success', "Security lockout for user {$targetUser->username} has been cleared.");
    expect($targetUser->fresh()->isLocked())->toBeFalse();
});

test('non-admin user cannot administratively unlock an account', function () {
    $nonAdmin = User::where('username', 'mycka')->first();
    $targetUser = User::where('username', 'alliah')->first();

    $targetUser->updateQuietly(['locked_until' => now()->addMinutes(15)]);

    $response = $this->actingAs($nonAdmin)->patch(route('admin.users.unlock', ['user' => $targetUser]));

    $response->assertNotFound();
    expect($targetUser->fresh()->isLocked())->toBeTrue();
});

test('admin can reset user password, revoking tokens and sessions', function () {
    $admin = User::where('username', 'allenglenn')->first();
    $targetUser = User::where('username', 'alliah')->first();

    // Create an API token
    $targetUser->createToken('test-mobile-token');
    expect($targetUser->tokens()->count())->toBe(1);

    // Create a web database session
    if (Schema::hasTable('sessions')) {
        DB::table('sessions')->insert([
            'id' => 'dummy_session_123',
            'user_id' => $targetUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Browser',
            'payload' => 'dummy_payload',
            'last_activity' => time(),
        ]);
        expect(DB::table('sessions')->where('user_id', $targetUser->id)->count())->toBe(1);
    }

    $newPassword = 'NewSecretPassword123';
    $response = $this->actingAs($admin)->patch(route('admin.users.updatePassword', ['user' => $targetUser]), [
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ]);

    $response->assertRedirect(route('admin.users'));
    $response->assertSessionHas('success', "Password for user {$targetUser->username} has been updated successfully.");

    $freshUser = $targetUser->fresh();
    expect(Hash::check($newPassword, $freshUser->password))->toBeTrue();
    expect($freshUser->remember_token)->toBeNull();
    expect($freshUser->tokens()->count())->toBe(0);

    if (Schema::hasTable('sessions')) {
        expect(DB::table('sessions')->where('user_id', $targetUser->id)->count())->toBe(0);
    }

    $activity = Activity::where('log_name', 'Users')
        ->where('event', 'password_reset')
        ->where('subject_id', $targetUser->id)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($admin->id);
    expect($activity->properties['action'])->toBe('admin_password_reset');
});

test('password reset requires minimum length and confirmation', function () {
    $admin = User::where('username', 'allenglenn')->first();
    $targetUser = User::where('username', 'alliah')->first();

    // Too short
    $resShort = $this->actingAs($admin)->patch(route('admin.users.updatePassword', ['user' => $targetUser]), [
        'password' => 'Short1',
        'password_confirmation' => 'Short1',
    ]);
    $resShort->assertSessionHasErrors(['password']);

    // Mismatched
    $resMismatch = $this->actingAs($admin)->patch(route('admin.users.updatePassword', ['user' => $targetUser]), [
        'password' => 'ValidPassword123',
        'password_confirmation' => 'MismatchPassword123',
    ]);
    $resMismatch->assertSessionHasErrors(['password']);
});

