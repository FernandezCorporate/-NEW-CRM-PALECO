<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
});

test('login endpoint is throttled after 10 requests per minute', function () {
    $payload = [
        'username' => 'wronguser',
        'password' => 'WrongPass123!',
        'device_name' => 'TestDevice',
    ];

    // First 10 attempts hit authentication logic
    for ($i = 1; $i <= 10; $i++) {
        $response = $this->postJson('/api/v1/login', $payload);
        expect($response->status())->not->toBe(429);
    }

    // 11th request is rejected by throttle:api-login
    $throttled = $this->postJson('/api/v1/login', $payload);
    $throttled->assertStatus(429);
    $throttled->assertJson([
        'success' => false,
        'message' => 'Too many login attempts. Please try again in a minute.',
    ]);
    expect($throttled->headers->has('Retry-After'))->toBeTrue();
});

test('general authenticated api route is throttled after 60 requests per minute', function () {
    $user = User::where('username', 'mycka')->first();
    Sanctum::actingAs($user, ['*']);

    // First 60 requests succeed
    for ($i = 1; $i <= 60; $i++) {
        $response = $this->getJson('/api/v1/user/profile');
        $response->assertOk();
        expect($response->headers->get('X-RateLimit-Limit'))->toBe('60');
    }

    // 61st request is throttled
    $throttled = $this->getJson('/api/v1/user/profile');
    $throttled->assertStatus(429);
    $throttled->assertJson([
        'success' => false,
        'message' => 'Too many requests. Please slow down.',
    ]);
    expect($throttled->headers->has('Retry-After'))->toBeTrue();
});

test('api rate limits are isolated per user id', function () {
    $user1 = User::where('username', 'mycka')->first();
    $user2 = User::where('username', 'ralph')->first();

    Sanctum::actingAs($user1, ['*']);

    // Exhaust user 1's limit
    for ($i = 1; $i <= 60; $i++) {
        $this->getJson('/api/v1/user/profile')->assertOk();
    }
    $this->getJson('/api/v1/user/profile')->assertStatus(429);

    // Switch to user 2 - should have full quota available
    Sanctum::actingAs($user2, ['*']);
    $response = $this->getJson('/api/v1/user/profile');
    $response->assertOk();
    expect($response->headers->get('X-RateLimit-Limit'))->toBe('60');
    expect((int) $response->headers->get('X-RateLimit-Remaining'))->toBe(59);
});

test('sync endpoints allow higher burst rate limit of 120 requests per minute', function () {
    $personnel = User::where('username', 'ralph')->first();
    Sanctum::actingAs($personnel, ['*']);

    // 120 burst requests to ticket action (fake ULID) with idempotency key
    for ($i = 1; $i <= 120; $i++) {
        $response = $this->withHeaders([
            'X-Idempotency-Key' => 'sync-test-'.$i,
        ])->patchJson('/api/v1/tickets/01JBCX5P3P8R6N8Z9Y5QW2E4T1/start');

        // Not throttled by 429
        expect($response->status())->not->toBe(429);
        expect($response->headers->get('X-RateLimit-Limit'))->toBe('120');
    }

    // 121st request is rejected by throttle:api-sync
    $throttled = $this->withHeaders([
        'X-Idempotency-Key' => 'sync-test-overflow',
    ])->patchJson('/api/v1/tickets/01JBCX5P3P8R6N8Z9Y5QW2E4T1/start');

    $throttled->assertStatus(429);
    $throttled->assertJson([
        'success' => false,
        'message' => 'Sync rate limit exceeded. Please wait before syncing more items.',
    ]);
    expect($throttled->headers->has('Retry-After'))->toBeTrue();
});

test('api and api-sync limiters operate in independent buckets', function () {
    $personnel = User::where('username', 'ralph')->first();
    Sanctum::actingAs($personnel, ['*']);

    // Exhaust the general api bucket (60 requests)
    for ($i = 1; $i <= 60; $i++) {
        $this->getJson('/api/v1/user/profile')->assertOk();
    }
    $this->getJson('/api/v1/user/profile')->assertStatus(429);

    // Sync route should still work because it belongs to api-sync tier
    $syncResponse = $this->withHeaders([
        'X-Idempotency-Key' => 'independent-bucket-key',
    ])->patchJson('/api/v1/tickets/01JBCX5P3P8R6N8Z9Y5QW2E4T1/start');

    expect($syncResponse->status())->not->toBe(429);
    expect($syncResponse->headers->get('X-RateLimit-Limit'))->toBe('120');
});
