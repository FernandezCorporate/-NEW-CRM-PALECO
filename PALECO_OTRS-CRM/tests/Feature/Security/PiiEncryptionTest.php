<?php

use App\Enums\ComplaintSources;
use App\Models\AccountRole;
use App\Models\Consumer;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('user PII attributes are encrypted at rest in the database and decryptable', function () {
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

test('consumer name and address are encrypted at rest in the database', function () {
    $consumer = Consumer::create([
        'acct_code' => '01-2001-3001',
        'acct_no' => '10002001',
        'name' => 'DELA CRUZ, JUAN P.',
        'address' => 'BGY. SAN PEDRO, PUERTO PRINCESA CITY',
        'status' => 'Active',
        'meter_serial' => 'PAL-888801',
    ]);

    // Inspect raw SQL row directly bypassing Eloquent casts
    $rawConsumer = DB::table('consumers')->where('id', $consumer->id)->first();

    expect($rawConsumer)->not->toBeNull();
    // Raw columns must NOT be plaintext
    expect($rawConsumer->name)->not->toBe('DELA CRUZ, JUAN P.');
    expect($rawConsumer->address)->not->toBe('BGY. SAN PEDRO, PUERTO PRINCESA CITY');

    // Raw columns must be valid ciphertext decryptable with Crypt
    expect(Crypt::decryptString($rawConsumer->name))->toBe('DELA CRUZ, JUAN P.');
    expect(Crypt::decryptString($rawConsumer->address))->toBe('BGY. SAN PEDRO, PUERTO PRINCESA CITY');

    // Eloquent model access must return decrypted plaintext
    expect($consumer->fresh()->name)->toBe('DELA CRUZ, JUAN P.');
    expect($consumer->fresh()->address)->toBe('BGY. SAN PEDRO, PUERTO PRINCESA CITY');
});

test('ticket consumer_contact is encrypted at rest in the database', function () {
    $consumer = Consumer::create([
        'acct_code' => '01-2003-3003',
        'acct_no' => '10002003',
        'name' => 'REYES, PEDRO T.',
        'address' => 'BGY. TINIGUIBAN, PUERTO PRINCESA CITY',
        'status' => 'Active',
        'meter_serial' => 'PAL-888803',
    ]);

    $officer = User::where('username', 'alliah')->first();
    $dept = Department::first();
    $cat = TicketCategory::first();

    $ticket = Ticket::create([
        'ticket_number' => 'TCK-2026-ENC-001',
        'consumer_id' => $consumer->id,
        'consumer_contact' => '09191234567',
        'complaint_source' => ComplaintSources::PHONE_CALL,
        'complaint_description' => 'Unscheduled power interruption',
        'category_id' => $cat->id,
        'purok' => 'Purok 1',
        'street' => 'Rizal Avenue',
        'barangay' => 'Tiniguiban',
        'department_id' => $dept->id,
        'created_by_id' => $officer->id,
        'reported_at' => now(),
    ]);

    $rawTicket = DB::table('tickets')->where('id', $ticket->id)->first();

    expect($rawTicket)->not->toBeNull();
    expect($rawTicket->consumer_contact)->not->toBe('09191234567');
    expect(Crypt::decryptString($rawTicket->consumer_contact))->toBe('09191234567');
    expect($ticket->fresh()->consumer_contact)->toBe('09191234567');
});

test('consumer scopeSearch finds records by encrypted name, account number, or code', function () {
    Consumer::create([
        'acct_code' => '01-2005-3005',
        'acct_no' => '10002005',
        'name' => 'FERNANDEZ, ALLEN GLENN F.',
        'address' => 'BGY. SAN JOSE, PUERTO PRINCESA CITY',
        'status' => 'Active',
        'meter_serial' => 'PAL-888805',
    ]);

    expect(Consumer::search('Fernandez')->count())->toBe(1);
    expect(Consumer::search('10002005')->count())->toBe(1);
    expect(Consumer::search('01-2005-3005')->count())->toBe(1);
});

test('admin creating a new user encrypts PII at rest and decrypts in web views', function () {
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

    // Raw row in DB
    $rawRow = DB::table('users')->where('username', 'mariadelacruz')->first();
    expect($rawRow)->not->toBeNull();
    expect($rawRow->first_name)->not->toBe('maria');
    expect(Crypt::decryptString($rawRow->first_name))->toBe('maria');
    expect(Crypt::decryptString($rawRow->email))->toBe('maria.delacruz@paleco.com');

    // Fresh user model
    $freshUser = User::where('username', 'mariadelacruz')->first();
    expect($freshUser->first_name)->toBe('maria');
    expect($freshUser->email)->toBe('maria.delacruz@paleco.com');

    // Web show view renders decrypted text
    $detailsResponse = $this->actingAs($admin)->get(route('admin.users.show', ['user' => $freshUser]));
    $detailsResponse->assertOk();
    $detailsResponse->assertSee('Maria');
    $detailsResponse->assertSee('Delacruz');
});
