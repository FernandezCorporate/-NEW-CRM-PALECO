<?php

use App\Models\AccountRole;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\TeamRoleSeeder;
use Database\Seeders\TeamSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Users\StoreUserRequest;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed(RoleSeeder::class);
    $this->seed(DepartmentSeeder::class);
    $this->seed(TeamRoleSeeder::class);
    $this->seed(TeamSeeder::class);
});

test('StoreUserRequest validates password requires at least 8 characters and mixed case', function () {
    $request = new StoreUserRequest();
    $rules = $request->rules();
    $messages = $request->messages();

    // 1. Password shorter than 8 characters fails
    $shortValidator = Validator::make([
        'password' => 'Pass1',
        'password_confirmation' => 'Pass1',
    ], ['password' => $rules['password']], $messages);

    expect($shortValidator->fails())->toBeTrue();
    expect($shortValidator->errors()->first('password'))->toContain('at least 8 characters');

    // 2. All lowercase fails mixed case requirement
    $lowerValidator = Validator::make([
        'password' => 'alllowercasepassword',
        'password_confirmation' => 'alllowercasepassword',
    ], ['password' => $rules['password']], $messages);

    expect($lowerValidator->fails())->toBeTrue();
    expect($lowerValidator->errors()->first('password'))->toBe('The password must contain at least one uppercase and one lowercase letter.');

    // 3. All uppercase fails mixed case requirement
    $upperValidator = Validator::make([
        'password' => 'ALLUPPERCASEPASSWORD',
        'password_confirmation' => 'ALLUPPERCASEPASSWORD',
    ], ['password' => $rules['password']], $messages);

    expect($upperValidator->fails())->toBeTrue();
    expect($upperValidator->errors()->first('password'))->toBe('The password must contain at least one uppercase and one lowercase letter.');

    // 4. Password confirmation mismatch fails
    $mismatchValidator = Validator::make([
        'password' => 'Password123',
        'password_confirmation' => 'DifferentPassword123',
    ], ['password' => $rules['password']], $messages);

    expect($mismatchValidator->fails())->toBeTrue();
    expect($mismatchValidator->errors()->first('password'))->toBe('The password confirmation does not match.');

    // 5. Valid mixed-case password with length >= 8 passes
    $validValidator = Validator::make([
        'password' => 'Password',
        'password_confirmation' => 'Password',
    ], ['password' => $rules['password']], $messages);

    expect($validValidator->passes())->toBeTrue();
});

test('UserSeeder correctly populates accounts with password "Password"', function () {
    $this->seed(UserSeeder::class);

    $users = User::all();
    expect($users->count())->toBe(4);

    foreach ($users as $user) {
        expect(Hash::check('Password', $user->password))->toBeTrue(
            "User {$user->username} failed authentication with seeded password 'Password'"
        );
        expect(Hash::check('password', $user->password))->toBeFalse(
            "User {$user->username} unexpectedly authenticated with old lowercase password"
        );
    }
});

test('Admin can create user through web portal with valid mixed-case password', function () {
    $this->seed(UserSeeder::class);
    $admin = User::where('username', 'allenglenn')->first();
    $role = AccountRole::where('slug_identifier', 'cwd_officer')->first();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'username' => 'newofficer',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'contact' => '09123456780',
        'role_id' => $role->id,
        'password' => 'Password',
        'password_confirmation' => 'Password',
    ]);

    $response->assertRedirect(route('admin.users'));
    $newUser = User::where('username', 'newofficer')->first();
    expect($newUser)->not->toBeNull();
    expect(Hash::check('Password', $newUser->password))->toBeTrue();
});

test('Admin cannot create user through web portal with lowercase only password', function () {
    $this->seed(UserSeeder::class);
    $admin = User::where('username', 'allenglenn')->first();
    $role = AccountRole::where('slug_identifier', 'cwd_officer')->first();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'username' => 'invaliduser',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'contact' => '09123456780',
        'role_id' => $role->id,
        'password' => 'lowercasepass',
        'password_confirmation' => 'lowercasepass',
    ]);

    $response->assertSessionHasErrors(['password']);
    expect(session('errors')->first('password'))->toBe('The password must contain at least one uppercase and one lowercase letter.');
    expect(User::where('username', 'invaliduser')->exists())->toBeFalse();
});
