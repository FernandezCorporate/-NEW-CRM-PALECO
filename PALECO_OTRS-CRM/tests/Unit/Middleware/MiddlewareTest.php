<?php

use App\Http\Middleware\CheckIfActive;
use App\Http\Middleware\HandleIdempotency;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('CheckIfActive allows active user to proceed', function () {
    $user = User::factory()->admin()->create(['is_active' => true]);
    Auth::login($user);

    $request = Request::create('/admin/dashboard', 'GET');
    $middleware = new CheckIfActive;

    $executed = false;
    $response = $middleware->handle($request, function ($req) use (&$executed) {
        $executed = true;

        return new Response('OK', 200);
    });

    expect($executed)->toBeTrue();
    expect($response->getStatusCode())->toBe(200);
});

test('CheckIfActive logs out deactivated user and redirects to portal', function () {
    $user = User::factory()->admin()->create(['is_active' => false]);
    Auth::login($user);

    $session = app('session.store');
    $request = Request::create('/admin/dashboard', 'GET');
    $request->setLaravelSession($session);

    $middleware = new CheckIfActive;
    $response = $middleware->handle($request, function () {
        return new Response('OK', 200);
    });

    expect(Auth::check())->toBeFalse();
    expect($response->getStatusCode())->toBe(302);
    expect($response->getTargetUrl())->toBe(route('portal'));
});

test('HandleIdempotency allows requests without idempotency header through', function () {
    $user = User::factory()->fieldPersonnel()->create();
    $request = Request::create('/api/v1/tickets/1/start', 'POST');
    $request->setUserResolver(fn () => $user);

    $middleware = new HandleIdempotency;
    $executed = false;
    $response = $middleware->handle($request, function () use (&$executed) {
        $executed = true;

        return new JsonResponse(['success' => true]);
    });

    expect($executed)->toBeTrue();
    expect($response->getStatusCode())->toBe(200);
});

test('HandleIdempotency rejects invalid key lengths', function () {
    $user = User::factory()->fieldPersonnel()->create();
    $request = Request::create('/api/v1/tickets/1/start', 'POST');
    $request->headers->set('X-Idempotency-Key', 'short-key');
    $request->setUserResolver(fn () => $user);

    $middleware = new HandleIdempotency;
    $response = $middleware->handle($request, fn () => new JsonResponse(['success' => true]));

    expect($response->getStatusCode())->toBe(422);
    $data = json_decode($response->getContent(), true);
    expect($data['message'])->toContain('between 16 and 64 characters');
});

test('HandleIdempotency stores and replays cached response for identical keys', function () {
    $user = User::factory()->fieldPersonnel()->create();
    $key = Str::uuid()->toString();

    $request = Request::create('/api/v1/tickets/1/start', 'POST', ['client_time' => '10:00']);
    $request->headers->set('X-Idempotency-Key', $key);
    $request->setUserResolver(fn () => $user);

    $middleware = new HandleIdempotency;

    $callCount = 0;
    $handler = function () use (&$callCount) {
        $callCount++;

        return new JsonResponse(['success' => true, 'counter' => $callCount], 200);
    };

    // First request executes handler and records response
    $firstResponse = $middleware->handle($request, $handler);
    expect($callCount)->toBe(1);
    expect($firstResponse->getStatusCode())->toBe(200);

    // Second request replays from database without executing handler again
    $secondResponse = $middleware->handle($request, $handler);
    expect($callCount)->toBe(1); // handler was NOT called again
    expect($secondResponse->getStatusCode())->toBe(200);

    $data = json_decode($secondResponse->getContent(), true);
    expect($data['counter'])->toBe(1);
});

test('HandleIdempotency detects payload mutation on reused key', function () {
    $user = User::factory()->fieldPersonnel()->create();
    $key = Str::uuid()->toString();

    $middleware = new HandleIdempotency;

    // First request
    $req1 = Request::create('/api/v1/tickets/1/start', 'POST', ['task' => 'initial']);
    $req1->headers->set('X-Idempotency-Key', $key);
    $req1->setUserResolver(fn () => $user);

    $middleware->handle($req1, fn () => new JsonResponse(['success' => true]));

    // Mutated second request with same key
    $req2 = Request::create('/api/v1/tickets/1/start', 'POST', ['task' => 'mutated_different_payload']);
    $req2->headers->set('X-Idempotency-Key', $key);
    $req2->setUserResolver(fn () => $user);

    $mutatedResponse = $middleware->handle($req2, fn () => new JsonResponse(['success' => true]));
    expect($mutatedResponse->getStatusCode())->toBe(422);
    $data = json_decode($mutatedResponse->getContent(), true);
    expect($data['message'])->toContain('Idempotency key has already been used with a different request payload');
});
