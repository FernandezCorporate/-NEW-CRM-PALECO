<?php

use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TeamRoleSeeder;
use Database\Seeders\TeamSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed(RoleSeeder::class);
    $this->seed(DepartmentSeeder::class);
    $this->seed(TeamRoleSeeder::class);
    $this->seed(TeamSeeder::class);
    $this->seed(UserSeeder::class);
});

test('Admin can reset a user password, revoking tokens and sessions and creating an audit activity log', function () {
    $admin = User::where('username', 'allenglenn')->first();
    $targetUser = User::where('username', 'alliah')->first();

    // 1. Create a dummy API token for the target user
    $token = $targetUser->createToken('test-mobile-token');
    expect($targetUser->tokens()->count())->toBe(1);

    // 2. Create a dummy web session for the target user in the sessions table
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

    // 3. Admin submits the PATCH request to reset password
    $newPassword = 'NewSecretPassword123';
    $response = $this->actingAs($admin)->patch(route('admin.users.updatePassword', ['user' => $targetUser]), [
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ]);

    $response->assertRedirect(route('admin.users'));
    $response->assertSessionHas('success', "Password for user {$targetUser->username} has been updated successfully.");

    // 4. Verify password was updated
    $freshUser = $targetUser->fresh();
    expect(Hash::check($newPassword, $freshUser->password))->toBeTrue();
    expect($freshUser->remember_token)->toBeNull();

    // 5. Verify API tokens were revoked
    expect($freshUser->tokens()->count())->toBe(0);

    // 6. Verify web database session was purged
    if (Schema::hasTable('sessions')) {
        expect(DB::table('sessions')->where('user_id', $targetUser->id)->count())->toBe(0);
    }

    // 7. Verify Spatie Activitylog logged the administrative password reset event
    $activity = Activity::where('log_name', 'Users')
        ->where('event', 'password_reset')
        ->where('subject_id', $targetUser->id)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($admin->id);
    expect($activity->description)->toContain($targetUser->username);
    expect($activity->properties['action'])->toBe('admin_password_reset');
    expect($activity->properties['target_user_id'])->toBe($targetUser->id);
    expect($activity->properties['username'])->toBe($targetUser->username);
});

test('Password reset is blind to old password and allows resetting to same password string', function () {
    $admin = User::where('username', 'allenglenn')->first();
    $targetUser = User::where('username', 'alliah')->first();

    // The seeded password is 'Password'
    $response = $this->actingAs($admin)->patch(route('admin.users.updatePassword', ['user' => $targetUser]), [
        'password' => 'Password',
        'password_confirmation' => 'Password',
    ]);

    $response->assertRedirect(route('admin.users'));
    $response->assertSessionHas('success', "Password for user {$targetUser->username} has been updated successfully.");
    expect(Hash::check('Password', $targetUser->fresh()->password))->toBeTrue();
});

test('Password reset requires confirmation and minimum complexity', function () {
    $admin = User::where('username', 'allenglenn')->first();
    $targetUser = User::where('username', 'alliah')->first();

    // Short password
    $resShort = $this->actingAs($admin)->patch(route('admin.users.updatePassword', ['user' => $targetUser]), [
        'password' => 'Short1',
        'password_confirmation' => 'Short1',
    ]);
    $resShort->assertSessionHasErrors(['password']);

    // Mismatched confirmation
    $resMismatch = $this->actingAs($admin)->patch(route('admin.users.updatePassword', ['user' => $targetUser]), [
        'password' => 'ValidPassword123',
        'password_confirmation' => 'MismatchPassword123',
    ]);
    $resMismatch->assertSessionHasErrors(['password']);
});
