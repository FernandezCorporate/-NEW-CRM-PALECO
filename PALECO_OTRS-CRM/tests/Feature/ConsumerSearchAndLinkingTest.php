<?php

use App\Enums\ComplaintSources;
use App\Models\AccountRole;
use App\Models\Consumer;
use App\Models\Department;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\External\ConsumerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure core role exists
    $this->cwdRole = AccountRole::firstOrCreate(
        ['slug_identifier' => 'cwd_officer'],
        ['role_name' => 'CWD Officer']
    );

    $this->cwdOfficer = User::firstOrCreate(
        ['username' => 'test_officer'],
        [
            'first_name' => 'Test',
            'last_name' => 'Officer',
            'contact' => '09123456789',
            'role_id' => $this->cwdRole->id,
            'password' => bcrypt('password'),
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

test('ConsumerService normalizes raw status codes accurately', function () {
    $service = app(ConsumerService::class);

    expect($service->normalizeStatus('A'))->toBe('Active');
    expect($service->normalizeStatus('a'))->toBe('Active');
    expect($service->normalizeStatus('ACTIVE'))->toBe('Active');
    expect($service->normalizeStatus('D'))->toBe('Deactivated');
    expect($service->normalizeStatus('d'))->toBe('Deactivated');
    expect($service->normalizeStatus('DEACTIVATED'))->toBe('Deactivated');
    expect($service->normalizeStatus('I'))->toBe('Inactive');
    expect($service->normalizeStatus('i'))->toBe('Inactive');
    expect($service->normalizeStatus('INACTIVE'))->toBe('Inactive');
    expect($service->normalizeStatus('X'))->toBe('Unknown');
    expect($service->normalizeStatus('P'))->toBe('Unknown');
    expect($service->normalizeStatus(null))->toBe('Unknown');
    expect($service->normalizeStatus(''))->toBe('Unknown');
});

test('Consumer model status attribute casts and persists friendly text', function () {
    $consumer = Consumer::create([
        'acct_code' => '99-1111-2222',
        'acct_no' => '99112233',
        'name' => 'FERNANDEZ, ALLEN GLENN F.',
        'address' => 'BGY. SAN JOSE, PUERTO PRINCESA CITY',
        'status' => 'A',
        'meter_serial' => 'PAL-12345',
    ]);

    expect($consumer->status)->toBe('Active');

    $consumer->status = 'D';
    $consumer->save();
    expect($consumer->fresh()->status)->toBe('Deactivated');

    $consumer->status = 'Z';
    $consumer->save();
    expect($consumer->fresh()->status)->toBe('Unknown');
});

test('Consumer model searchByNameAndMeter scope handles single name and Lastname,Firstname formats', function () {
    Consumer::create([
        'acct_code' => '99-0001-0001',
        'acct_no' => '99000001',
        'name' => 'FERNANDEZ, ALLEN GLENN F.',
        'address' => 'BGY. TINIGUIBAN, PUERTO PRINCESA CITY',
        'status' => 'Active',
        'meter_serial' => 'PAL-99991',
    ]);

    Consumer::create([
        'acct_code' => '99-0002-0002',
        'acct_no' => '99000002',
        'name' => 'FERNANDEZ, MARIA CLARA',
        'address' => 'BGY. SAN PEDRO, PUERTO PRINCESA CITY',
        'status' => 'Active',
        'meter_serial' => 'PAL-99992',
    ]);

    // Search single lastname
    $results = Consumer::query()->searchByNameAndMeter('Fernandez')->get();
    expect($results)->toHaveCount(2);

    // Search Lastname,Firstname filter
    $filtered = Consumer::query()->searchByNameAndMeter('Fernandez,Alle')->get();
    expect($filtered)->toHaveCount(1);
    expect($filtered->first()->name)->toBe('FERNANDEZ, ALLEN GLENN F.');

    // Search with meter serial
    $meterFiltered = Consumer::query()->searchByNameAndMeter('Fernandez', 'PAL-99992')->get();
    expect($meterFiltered)->toHaveCount(1);
    expect($meterFiltered->first()->name)->toBe('FERNANDEZ, MARIA CLARA');

    // Search with meter serial alone
    $meterAlone = Consumer::query()->searchByNameAndMeter(null, 'PAL-99991')->get();
    expect($meterAlone)->toHaveCount(1);
    expect($meterAlone->first()->name)->toBe('FERNANDEZ, ALLEN GLENN F.');
});

test('ConsumerService searchAccounts forces paleco_db and normalizes API status', function () {
    Http::fake([
        'https://api.paleco.net/api/v1/accounts/search*' => Http::response([
            'success' => true,
            'message' => 'Accounts retrieved successfully.',
            'data' => [
                [
                    'acct_code' => '02-0504-8538',
                    'acct_no' => '10293847',
                    'name' => 'DELA CRUZ, JUAN',
                    'address' => 'BGY. SAN JOSE, PUERTO PRINCESA CITY',
                    'status' => 'A',
                    'meter_serial' => 'PAL-778899',
                ],
                [
                    'acct_code' => '02-0504-8539',
                    'acct_no' => '10293848',
                    'name' => 'DELA CRUZ, PEDRO',
                    'address' => 'BGY. SAN MANUEL, PUERTO PRINCESA CITY',
                    'status' => 'D',
                    'meter_serial' => 'PAL-778800',
                ],
            ],
            'has_more' => true,
        ], 200),
    ]);

    $service = app(ConsumerService::class);
    $response = $service->searchAccounts('Dela Cruz', 'PAL-778899', 30);

    expect($response['source'])->toBe('api');
    expect($response['has_more'])->toBeTrue();
    expect($response['data'])->toHaveCount(2);
    expect($response['data'][0]['status'])->toBe('Active');
    expect($response['data'][1]['status'])->toBe('Deactivated');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'https://api.paleco.net/api/v1/accounts/search')
            && $request['database'] === 'paleco_db'
            && $request['lastname'] === 'Dela Cruz'
            && $request['meter_serial'] === 'PAL-778899'
            && (int) $request['limit'] === 30;
    });
});

test('ConsumerService searchAccounts falls back to local database when API fails', function () {
    Http::fake([
        'https://api.paleco.net/api/v1/accounts/search*' => Http::response(null, 503),
    ]);

    Consumer::create([
        'acct_code' => '99-8888-7777',
        'acct_no' => '99887766',
        'name' => 'SANTOS, ROBERTO',
        'address' => 'BGY. SAN JOSE, PUERTO PRINCESA CITY',
        'status' => 'Active',
        'meter_serial' => 'PAL-554433',
    ]);

    $service = app(ConsumerService::class);
    $response = $service->searchAccounts('Santos');

    expect($response['source'])->toBe('local');
    expect($response['has_more'])->toBeFalse();
    expect($response['data'])->toHaveCount(1);
    expect($response['data'][0]['name'])->toBe('SANTOS, ROBERTO');
});

test('GET /cwd/consumers/search requires authentication and validates parameters', function () {
    // Unauthenticated request
    $guestResponse = $this->get(route('cwd.consumers.search', ['lastname' => 'Fernandez']));
    expect($guestResponse->isRedirect())->toBeTrue();

    // Authenticated as CWD Officer with invalid lastname length
    $invalidResponse = $this->actingAs($this->cwdOfficer)
        ->getJson(route('cwd.consumers.search', ['lastname' => 'F']));

    expect($invalidResponse->status())->toBe(422);
    expect($invalidResponse->json('success'))->toBeFalse();
    expect($invalidResponse->json('errors.lastname'))->not->toBeNull();

    // Authenticated with neither lastname nor meter_serial
    $missingResponse = $this->actingAs($this->cwdOfficer)
        ->getJson(route('cwd.consumers.search'));

    expect($missingResponse->status())->toBe(422);
    expect($missingResponse->json('success'))->toBeFalse();

    // Authenticated with valid parameters (lastname and meter_serial)
    Http::fake([
        'https://api.paleco.net/api/v1/accounts/search*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'acct_code' => '02-0504-8538',
                    'acct_no' => '10293847',
                    'name' => 'FERNANDEZ, ALLEN GLENN',
                    'address' => 'PUERTO PRINCESA CITY',
                    'status' => 'A',
                    'meter_serial' => 'PAL-112233',
                ],
            ],
            'has_more' => false,
        ], 200),
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->getJson(route('cwd.consumers.search', [
            'lastname' => 'Fernandez,Alle',
            'meter_serial' => 'PAL-112233',
        ]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'count' => 1,
            'has_more' => false,
            'source' => 'api',
        ])
        ->assertJsonPath('data.0.status', 'Active');

    // Authenticated with meter_serial alone
    $meterResponse = $this->actingAs($this->cwdOfficer)
        ->getJson(route('cwd.consumers.search', [
            'meter_serial' => 'PAL-112233',
        ]));

    $meterResponse->assertOk()
        ->assertJson([
            'success' => true,
            'count' => 1,
            'has_more' => false,
            'source' => 'api',
        ]);
});

test('ConsumerService searchAccounts supports meter_serial alone without lastname', function () {
    Http::fake([
        'https://api.paleco.net/api/v1/accounts/search*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'acct_code' => '02-0504-8538',
                    'acct_no' => '10293847',
                    'name' => 'DELA CRUZ, JUAN',
                    'address' => 'BGY. SAN JOSE, PUERTO PRINCESA CITY',
                    'status' => 'A',
                    'meter_serial' => 'PAL-778899',
                ],
            ],
            'has_more' => false,
        ], 200),
    ]);

    $service = app(ConsumerService::class);
    $response = $service->searchAccounts(null, 'PAL-778899', 30);

    expect($response['source'])->toBe('api');
    expect($response['data'])->toHaveCount(1);
    expect($response['data'][0]['meter_serial'])->toBe('PAL-778899');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'https://api.paleco.net/api/v1/accounts/search')
            && $request['database'] === 'paleco_db'
            && ! isset($request['lastname'])
            && $request['meter_serial'] === 'PAL-778899';
    });
});

test('Ticket creation successfully links consumer selected from search', function () {
    Http::fake([
        'https://api.paleco.net/api/v1/account/02-0504-8538' => Http::response([
            'success' => true,
            'data' => [
                'acct_code' => '02-0504-8538',
                'acct_no' => '10293847',
                'name' => 'FERNANDEZ, ALLEN GLENN',
                'address' => 'BGY. SAN JOSE, PUERTO PRINCESA CITY',
                'status' => 'A',
                'meter_serial' => 'PAL-112233',
            ],
        ], 200),
    ]);

    $ticketPayload = [
        'consumer_contact' => '09123456789',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'complaint_description' => 'Power outage reported at premise.',
        'purok' => 'Purok Pag-asa',
        'street' => 'National Highway',
        'barangay' => 'San Jose',
        'department_id' => $this->department->id,
        'category_id' => $this->category->id,
        'other_category' => false,
        'link_consumer' => true,
        'account_code' => '02-0504-8538',
    ];

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.store'), $ticketPayload);

    $response->assertRedirect();

    $consumer = Consumer::where('acct_code', '02-0504-8538')->first();
    expect($consumer)->not->toBeNull();
    expect($consumer->status)->toBe('Active');
    expect($consumer->name)->toBe('FERNANDEZ, ALLEN GLENN');
});
