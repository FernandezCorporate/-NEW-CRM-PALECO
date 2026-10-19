<?php

use App\Models\Consumer;
use App\Models\User;
use App\Services\External\PalecoBillingApiService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('cwd officer can view consumers directory', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();

    $this->actingAs($cwdOfficer)->get(route('cwd.consumers'))
        ->assertStatus(200)
        ->assertViewIs('cwd.pages.consumerManagement')
        ->assertViewHas('consumers');
});

test('unauthorized roles including admin cannot access cwd consumers directory', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->supervisor()->create();
    $fieldPersonnel = User::factory()->fieldPersonnel()->create();

    $this->actingAs($admin)->get(route('cwd.consumers'))
        ->assertStatus(404);

    $this->actingAs($supervisor)->get(route('cwd.consumers'))
        ->assertStatus(404);

    $this->actingAs($fieldPersonnel)->get(route('cwd.consumers'))
        ->assertStatus(404);
});

test('cwd officer can view specific consumer profile details', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $consumer = Consumer::factory()->create();

    $response = $this->actingAs($cwdOfficer)->get(route('cwd.consumers.show', $consumer));

    $response->assertStatus(200);
    $response->assertViewIs('cwd.pages.consumerDetails');
    $response->assertViewHas('consumer');
});

test('verify endpoint returns local consumer data if already existing', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $consumer = Consumer::factory()->create([
        'acct_code' => '1122334455',
        'name' => 'Maria Santos',
    ]);

    $response = $this->actingAs($cwdOfficer)->getJson(route('cwd.consumers.verify', ['accountCode' => '1122334455']));

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'consumer' => [
                'acct_code' => '1122334455',
            ],
        ]);
});

test('verify endpoint calls billing API when consumer is not stored locally', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();

    Http::fake([
        'https://api.paleco.net/api/v1/account/*' => Http::response([
            'success' => true,
            'data' => [
                'account_code' => '9988776655',
                'full_name' => 'EXTERNAL CONSUMER RECORD',
                'status' => 'ACTIVE',
                'meter_serial' => 'MTR-9988',
                'address' => 'Barangay San Pedro',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($cwdOfficer)->getJson(route('cwd.consumers.verify', ['accountCode' => '9988776655']));

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'consumer' => [
                'account_code' => '9988776655',
                'full_name' => 'EXTERNAL CONSUMER RECORD',
            ],
        ]);
});

test('search endpoint validates required search parameters', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();

    $response = $this->actingAs($cwdOfficer)->getJson(route('cwd.consumers.search'));

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

test('search endpoint queries billing api and returns matching accounts', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();

    Http::fake([
        'https://api.paleco.net/api/v1/accounts/search*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'account_code' => '1234567890',
                    'full_name' => 'DELA CRUZ, JUAN',
                    'status' => 'CONNECTED',
                    'meter_serial' => 'MTR-12345',
                ],
            ],
            'has_more' => false,
        ], 200),
    ]);

    $response = $this->actingAs($cwdOfficer)->getJson(route('cwd.consumers.search', ['lastname' => 'Cruz']));

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'count' => 1,
            'data' => [
                [
                    'account_code' => '1234567890',
                    'full_name' => 'DELA CRUZ, JUAN',
                ],
            ],
        ]);
});

test('billing API TLS verification stays on unless PALECO_API_VERIFY_SSL is explicitly false', function (?string $envValue, bool $expected) {
    if ($envValue === null) {
        unset($_SERVER['PALECO_API_VERIFY_SSL']);
    } else {
        $_SERVER['PALECO_API_VERIFY_SSL'] = $envValue;
    }

    try {
        $services = require config_path('services.php');
    } finally {
        unset($_SERVER['PALECO_API_VERIFY_SSL']);
    }

    expect($services['paleco']['verify_ssl'])->toBe($expected);
})->with([
    'not set' => [null, true],
    'blank' => ['', true],
    'true' => ['true', true],
    'false' => ['false', false],
]);

test('billing API logs a warning only while the TLS emergency switch is off', function () {
    Http::fake([
        'https://api.paleco.net/api/v1/accounts/search*' => Http::response([
            'success' => true,
            'data' => [],
            'has_more' => false,
        ], 200),
    ]);
    Log::spy();

    $service = app(PalecoBillingApiService::class);

    $service->searchAccounts('Cruz');
    Log::shouldNotHaveReceived('warning');

    config(['services.paleco.verify_ssl' => false]);
    $result = $service->searchAccounts('Cruz');

    expect($result['source'])->toBe('api');
    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'PALECO_API_VERIFY_SSL=false'));
});
