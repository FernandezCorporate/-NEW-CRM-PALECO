<?php

use App\Enums\ComplaintSources;
use App\Models\AccountRole;
use App\Models\Consumer;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\Consumers\ConsumerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->role = AccountRole::firstOrCreate(
        ['slug_identifier' => 'cwd_officer'],
        ['role_name' => 'CWD Officer']
    );

    $this->officer = User::firstOrCreate(
        ['username' => 'officer_test'],
        [
            'first_name' => 'Test',
            'last_name' => 'Officer',
            'contact' => '09123456789',
            'role_id' => $this->role->id,
            'password' => bcrypt('Secret123'),
            'is_active' => 1,
        ]
    );

    $this->department = Department::firstOrCreate(
        ['dept_name' => 'Technical Operations'],
        ['dept_desc' => 'Technical operations department']
    );

    $this->category = TicketCategory::firstOrCreate(
        ['category_name' => 'Power Outage'],
        ['category_desc' => 'Total or partial power outage']
    );
});

test('consumer name and address are encrypted at rest in the database and decryptable via Crypt', function () {
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

    $ticket = Ticket::create([
        'ticket_number' => 'TCK-2026-ENC-001',
        'consumer_id' => $consumer->id,
        'consumer_contact' => '09191234567',
        'complaint_source' => ComplaintSources::PHONE_CALL,
        'complaint_description' => 'Unscheduled power interruption',
        'category_id' => $this->category->id,
        'purok' => 'Purok 1',
        'street' => 'Rizal Avenue',
        'barangay' => 'Tiniguiban',
        'department_id' => $this->department->id,
        'created_by_id' => $this->officer->id,
        'reported_at' => now(),
    ]);

    // Inspect raw SQL row
    $rawTicket = DB::table('tickets')->where('id', $ticket->id)->first();

    expect($rawTicket)->not->toBeNull();
    // Raw contact must NOT be plaintext
    expect($rawTicket->consumer_contact)->not->toBe('09191234567');
    // Raw contact must be valid ciphertext decryptable with Crypt
    expect(Crypt::decryptString($rawTicket->consumer_contact))->toBe('09191234567');

    // Eloquent model access must return decrypted plaintext
    expect($ticket->fresh()->consumer_contact)->toBe('09191234567');
});

test('consumer details service aggregates decrypted last known contact from tickets', function () {
    $consumer = Consumer::create([
        'acct_code' => '01-2004-3004',
        'acct_no' => '10002004',
        'name' => 'GARCIA, ANA L.',
        'address' => 'BGY. SAN MANUEL, PUERTO PRINCESA CITY',
        'status' => 'Active',
        'meter_serial' => 'PAL-888804',
    ]);

    Ticket::create([
        'ticket_number' => 'TCK-2026-ENC-002',
        'consumer_id' => $consumer->id,
        'consumer_contact' => '09209876543',
        'complaint_source' => ComplaintSources::WALK_IN,
        'complaint_description' => 'Low voltage fluctuation',
        'category_id' => $this->category->id,
        'purok' => 'Purok 2',
        'street' => 'National Highway',
        'barangay' => 'San Manuel',
        'department_id' => $this->department->id,
        'created_by_id' => $this->officer->id,
        'reported_at' => now(),
    ]);

    $service = app(ConsumerService::class);
    $details = $service->getConsumerDetails(new Request, $consumer);

    expect($details['consumer']->address)->toBe('BGY. SAN MANUEL, PUERTO PRINCESA CITY');
    expect($details['lastKnownContact'])->toBe('09209876543');
});

test('consumer scopeSearch finds records by name, account number, or code', function () {
    Consumer::create([
        'acct_code' => '01-2005-3005',
        'acct_no' => '10002005',
        'name' => 'FERNANDEZ, ALLEN GLENN F.',
        'address' => 'BGY. SAN JOSE, PUERTO PRINCESA CITY',
        'status' => 'Active',
        'meter_serial' => 'PAL-888805',
    ]);

    // Search by partial name
    expect(Consumer::search('Fernandez')->count())->toBe(1);

    // Search by exact name
    expect(Consumer::search('FERNANDEZ, ALLEN GLENN F.')->count())->toBe(1);

    // Search by account number
    expect(Consumer::search('10002005')->count())->toBe(1);

    // Search by account code
    expect(Consumer::search('01-2005-3005')->count())->toBe(1);
});
