<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
});

test('unauthenticated guests can view role selection portal', function () {
    $response = $this->get(route('portal'));

    $response->assertOk();
    $response->assertViewIs('auth.roleSelection');
});

test('unauthenticated guests can view login form for valid roles', function (string $role) {
    $response = $this->get(route('portal.login', ['role' => $role]));

    $response->assertOk();
    $response->assertViewIs('auth.login');
    $response->assertViewHas('role', $role);
})->with(['admin', 'cwd_officer']);

test('guests are redirected to portal when accessing an invalid role login form', function () {
    $response = $this->get(route('portal.login', ['role' => 'invalid_role']));

    $response->assertRedirect(route('portal'));
    $response->assertSessionHasErrors('error');
});

test('admin can authenticate successfully and is redirected to admin dashboard', function () {
    $response = $this->post(route('attemptLogin', ['role' => 'admin']), [
        'username' => 'allenglenn',
        'password' => 'Password',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticated();
    expect(auth()->user()->username)->toBe('allenglenn');
});

test('cwd officer can authenticate successfully and is redirected to cwd dashboard', function () {
    $response = $this->post(route('attemptLogin', ['role' => 'cwd_officer']), [
        'username' => 'alliah',
        'password' => 'Password',
    ]);

    $response->assertRedirect(route('cwd.dashboard'));
    $this->assertAuthenticated();
    expect(auth()->user()->username)->toBe('alliah');
});

test('web login fails when providing incorrect password', function () {
    $response = $this->from(route('portal.login', ['role' => 'admin']))
        ->post(route('attemptLogin', ['role' => 'admin']), [
            'username' => 'allenglenn',
            'password' => 'WrongPassword',
        ]);

    $response->assertRedirect(route('portal.login', ['role' => 'admin']));
    $response->assertSessionHasErrors('error');
    $this->assertGuest();
});

test('mobile roles cannot authenticate via web login portal', function (string $username, string $role) {
    $response = $this->from(route('portal.login', ['role' => 'admin']))
        ->post(route('attemptLogin', ['role' => 'admin']), [
            'username' => $username,
            'password' => 'Password',
        ]);

    $response->assertRedirect(route('portal.login', ['role' => 'admin']));
    $response->assertSessionHasErrors('error');
    $this->assertGuest();
})->with([
    ['mycka', 'supervisor'],
    ['ralph', 'field_personnel'],
]);

test('deactivated user cannot authenticate via web login portal', function () {
    $admin = User::where('username', 'allenglenn')->first();
    $admin->update(['is_active' => false]);

    $response = $this->from(route('portal.login', ['role' => 'admin']))
        ->post(route('attemptLogin', ['role' => 'admin']), [
            'username' => 'allenglenn',
            'password' => 'Password',
        ]);

    $response->assertRedirect(route('portal.login', ['role' => 'admin']));
    $response->assertSessionHasErrors('error');
    $this->assertGuest();
});

test('account locks out after consecutive failed login attempts', function () {
    $admin = User::where('username', 'allenglenn')->first();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('attemptLogin', ['role' => 'admin']), [
            'username' => 'allenglenn',
            'password' => 'WrongPassword',
        ]);
    }

    $admin->refresh();
    expect($admin->locked_until)->not->toBeNull()
        ->and($admin->isLocked())->toBeTrue();

    // Now even correct password fails due to lockout
    $response = $this->post(route('attemptLogin', ['role' => 'admin']), [
        'username' => 'allenglenn',
        'password' => 'Password',
    ]);

    $response->assertSessionHasErrors('error');
    $this->assertGuest();
});

test('authenticated user can log out successfully', function () {
    $admin = User::where('username', 'allenglenn')->first();

    $response = $this->actingAs($admin)->post(route('logout'));

    $response->assertRedirect(route('portal'));
    $this->assertGuest();
});

test('root endpoint redirects authenticated admin to admin dashboard', function () {
    $admin = User::where('username', 'allenglenn')->first();

    $response = $this->actingAs($admin)->get('/');

    $response->assertRedirect(route('admin.dashboard'));
});

test('root endpoint redirects authenticated cwd officer to cwd dashboard', function () {
    $officer = User::where('username', 'alliah')->first();

    $response = $this->actingAs($officer)->get('/');

    $response->assertRedirect(route('cwd.dashboard'));
});
