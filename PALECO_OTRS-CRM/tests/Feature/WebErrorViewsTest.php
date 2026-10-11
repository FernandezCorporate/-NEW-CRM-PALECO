<?php

use App\Models\AccountRole;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['database.default' => 'view_test', 'database.connections.view_test' => [
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

    Schema::create('account_roles', function (Blueprint $table) {
        $table->id();
        $table->string('role_name');
        $table->string('slug_identifier')->unique();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('username')->unique();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('contact');
        $table->foreignId('role_id')->nullable();
        $table->string('password');
        $table->tinyInteger('is_active')->default(1);
        $table->timestamps();
    });
});

test('unauthenticated web visitor visiting non-existent route sees branded 404 page with portal fallback', function () {
    $response = $this->get('/non-existent-route-for-testing-404');

    $response->assertStatus(404)
        ->assertSee('PALECO CRM')
        ->assertSee('Palawan Electric Cooperative')
        ->assertSee('Error 404')
        ->assertSee('Page or Record Not Found')
        ->assertSee('Return to Previous Page')
        ->assertSee('Back to Portal')
        ->assertSee(route('portal'));
});

test('authenticated web user visiting non-existent route sees branded 404 page with dashboard fallback', function () {
    $role = AccountRole::create(['id' => 1, 'role_name' => 'Admin', 'slug_identifier' => 'admin']);
    $user = User::create([
        'id' => (string) Str::ulid(),
        'username' => 'test_admin_404',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'contact' => '09123456789',
        'password' => bcrypt('password'),
        'role_id' => $role->id,
    ]);

    $response = $this->actingAs($user)->get('/non-existent-route-authenticated');

    $response->assertStatus(404)
        ->assertSee('PALECO CRM')
        ->assertSee('Error 404')
        ->assertSee('Page or Record Not Found')
        ->assertSee('Return to Previous Page')
        ->assertSee('Go to Dashboard')
        ->assertSee(route('dashboard'));
});

test('403 error renders branded access forbidden template', function () {
    $view = view('errors.403')->render();

    expect($view)->toContain('PALECO CRM')
        ->and($view)->toContain('Error 403')
        ->and($view)->toContain('Access Forbidden')
        ->and($view)->toContain('Return to Previous Page');
});
