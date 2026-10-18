<?php

use App\Models\AccountRole;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TeamRoleSeeder;
use Database\Seeders\TeamSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed(RoleSeeder::class);
    $this->seed(DepartmentSeeder::class);
    $this->seed(TeamRoleSeeder::class);
    $this->seed(TeamSeeder::class);
});

test('user PII attributes are encrypted at rest in the database', function () {
    $this->seed(UserSeeder::class);

    // 1. Inspect raw SQL row directly bypassing Eloquent casts
    $rawAdmin = DB::table('users')->where('username', 'allenglenn')->first();

    expect($rawAdmin)->not->toBeNull();
    // Raw column should NOT contain plaintext name
    expect($rawAdmin->first_name)->not->toBe('allen glenn');
    expect($rawAdmin->last_name)->not->toBe('fernandez');
    expect($rawAdmin->contact)->not->toBe('09123456789');

    // Raw column must be valid ciphertext decryptable by Crypt
    expect(Crypt::decryptString($rawAdmin->first_name))->toBe('allen glenn');
    expect(Crypt::decryptString($rawAdmin->last_name))->toBe('fernandez');
    expect(Crypt::decryptString($rawAdmin->contact))->toBe('09123456789');

    // 2. Inspect through Eloquent model
    $user = User::where('username', 'allenglenn')->first();
    expect($user->first_name)->toBe('allen glenn');
    expect($user->last_name)->toBe('fernandez');
    expect($user->contact)->toBe('09123456789');
    expect($user->full_name)->toBe('Allen Glenn Fernandez');
});

test('admin creating a new user encrypts PII at rest and decrypts in web views', function () {
    $this->seed(UserSeeder::class);
    $admin = User::where('username', 'allenglenn')->first();
    $role = AccountRole::where('slug_identifier', 'cwd_officer')->first();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'username' => 'mariadelacruz',
        'first_name' => 'maria',
        'middle_name' => 'santos',
        'last_name' => 'delacruz',
        'name_ext' => 'jr',
        'email' => 'maria.delacruz@paleco.com',
        'contact' => '09181234567',
        'role_id' => $role->id,
        'password' => 'SecurePass123',
        'password_confirmation' => 'SecurePass123',
    ]);

    $response->assertRedirect(route('admin.users'));

    // Check raw DB row
    $rawRow = DB::table('users')->where('username', 'mariadelacruz')->first();
    expect($rawRow)->not->toBeNull();
    expect($rawRow->first_name)->not->toBe('maria');
    expect(Crypt::decryptString($rawRow->first_name))->toBe('maria');
    expect(Crypt::decryptString($rawRow->middle_name))->toBe('santos');
    expect(Crypt::decryptString($rawRow->last_name))->toBe('delacruz');
    expect(Crypt::decryptString($rawRow->email))->toBe('maria.delacruz@paleco.com');
    expect(Crypt::decryptString($rawRow->contact))->toBe('09181234567');

    // Check Eloquent model and view
    $freshUser = User::where('username', 'mariadelacruz')->first();
    expect($freshUser->first_name)->toBe('maria');
    expect($freshUser->middle_name)->toBe('santos');
    expect($freshUser->last_name)->toBe('delacruz');
    expect($freshUser->email)->toBe('maria.delacruz@paleco.com');
    expect($freshUser->contact)->toBe('09181234567');

    // Verify user details page renders decrypted text
    $detailsResponse = $this->actingAs($admin)->get(route('admin.users.show', ['user' => $freshUser]));
    $detailsResponse->assertOk();
    $detailsResponse->assertSee('Maria');
    $detailsResponse->assertSee('Delacruz');
    $detailsResponse->assertSee('09181234567');
});

test('admin user management search finds encrypted users by name and contact', function () {
    $this->seed(UserSeeder::class);
    $admin = User::where('username', 'allenglenn')->first();

    // Search by first name "alliah"
    $searchResponse = $this->actingAs($admin)->get(route('admin.users', ['search' => 'alliah']));
    $searchResponse->assertOk();
    $searchResponse->assertSee('Alliah Officer');
    $searchResponse->assertDontSee('ralph');

    // Search by last name "officer"
    $searchResponse = $this->actingAs($admin)->get(route('admin.users', ['search' => 'officer']));
    $searchResponse->assertOk();
    $searchResponse->assertSee('Alliah Officer');
    $searchResponse->assertDontSee('ralph');
});

test('admin user management sorting by first and last name works with encryption', function () {
    $this->seed(UserSeeder::class);
    $admin = User::where('username', 'allenglenn')->first();

    $sortResponse = $this->actingAs($admin)->get(route('admin.users', ['sort' => 'first_nameASC']));
    $sortResponse->assertOk();

    /** @var \Illuminate\Pagination\LengthAwarePaginator $users */
    $users = $sortResponse->viewData('users');
    $names = $users->pluck('first_name')->toArray();

    $sortedNames = $names;
    natcasesort($sortedNames);
    expect($names)->toEqual(array_values($sortedNames));
});

test('duplicate email validation works with encrypted emails', function () {
    $this->seed(UserSeeder::class);
    $admin = User::where('username', 'allenglenn')->first();
    $role = AccountRole::where('slug_identifier', 'cwd_officer')->first();

    // First user with email
    User::create([
        'username' => 'firstuser',
        'first_name' => 'first',
        'last_name' => 'user',
        'email' => 'duplicate@example.com',
        'contact' => '09123456781',
        'role_id' => $role->id,
        'password' => 'Password123',
    ]);

    // Try creating second user with duplicate email
    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'username' => 'seconduser',
        'first_name' => 'second',
        'last_name' => 'user',
        'email' => 'DUPLICATE@example.com',
        'contact' => '09123456782',
        'role_id' => $role->id,
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ]);

    $response->assertSessionHasErrors(['email']);
    expect(session('errors')->first('email'))->toBe('This email address is already registered in the system.');
});
