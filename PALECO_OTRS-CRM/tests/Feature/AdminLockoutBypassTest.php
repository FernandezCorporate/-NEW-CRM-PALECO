<?php

use App\Models\AccountRole;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TeamRoleSeeder;
use Database\Seeders\TeamSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed(RoleSeeder::class);
    $this->seed(DepartmentSeeder::class);
    $this->seed(TeamRoleSeeder::class);
    $this->seed(TeamSeeder::class);
    $this->seed(UserSeeder::class);
});

test('Admin can administratively unlock a locked user account and clear rate limiter keys', function () {
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

test('Admin unlocking from details page redirects back to user details page', function () {
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

test('Non-admin user cannot administratively unlock an account', function () {
    $nonAdmin = User::where('username', 'mycka')->first();
    $targetUser = User::where('username', 'alliah')->first();

    $targetUser->updateQuietly(['locked_until' => now()->addMinutes(15)]);

    $response = $this->actingAs($nonAdmin)->patch(route('admin.users.unlock', ['user' => $targetUser]));

    $response->assertNotFound();
    expect($targetUser->fresh()->isLocked())->toBeTrue();
});

test('User management view displays locked badge and unlock button for locked users', function () {
    $admin = User::where('username', 'allenglenn')->first();
    $targetUser = User::where('username', 'alliah')->first();

    // Verify view when NOT locked
    $responseClean = $this->actingAs($admin)->get(route('admin.users'));
    $responseClean->assertOk();
    $responseClean->assertDontSee('Locked (');

    // Lock the account and verify view displays badge and unlock button
    $targetUser->updateQuietly(['locked_until' => now()->addMinutes(15)]);

    $responseLocked = $this->actingAs($admin)->get(route('admin.users'));
    $responseLocked->assertOk();
    $responseLocked->assertSee('Locked (');
    $responseLocked->assertSee(route('admin.users.unlock', ['user' => $targetUser]), false);

    // Verify user details page displays Unlock Account button and Security Lockout row
    $responseDetails = $this->actingAs($admin)->get(route('admin.users.show', ['user' => $targetUser]));
    $responseDetails->assertOk();
    $responseDetails->assertSee('Unlock Account');
    $responseDetails->assertSee('Security Lockout');
});
