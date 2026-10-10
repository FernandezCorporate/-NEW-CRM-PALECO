<?php

use App\Enums\TicketStatus;
use App\Models\IdempotencyRecord;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['database.default' => 'offline_test', 'database.connections.offline_test' => [
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
        $table->string('middle_name')->nullable();
        $table->string('last_name');
        $table->string('name_ext')->nullable();
        $table->string('email')->nullable();
        $table->string('contact');
        $table->foreignId('role_id')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->string('password');
        $table->tinyInteger('is_active')->default(1);
        $table->timestamps();
    });

    Schema::create('teams', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('team_name');
        $table->string('team_desc')->nullable();
        $table->time('shift_start');
        $table->time('shift_end');
        $table->foreignId('department_id')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('team_members', function (Blueprint $table) {
        $table->string('user_id', 26);
        $table->string('team_id', 26);
        $table->foreignId('team_role_id');
        $table->primary(['user_id', 'team_id']);
        $table->timestamps();
    });

    Schema::create('ticket_categories', function (Blueprint $table) {
        $table->id();
        $table->string('category_name');
        $table->string('category_desc')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('tickets', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('ticket_number', 30)->unique();
        $table->string('parent_ticket_id', 26)->nullable();
        $table->string('consumer_id', 26)->nullable();
        $table->string('consumer_contact', 20)->nullable();
        $table->string('complaint_source', 30);
        $table->text('complaint_description')->nullable();
        $table->foreignId('category_id')->nullable();
        $table->boolean('other_category')->default(false);
        $table->string('other_category_name')->nullable();
        $table->string('purok')->nullable();
        $table->string('street')->nullable();
        $table->string('barangay');
        $table->string('landmark')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->string('team_id', 26)->nullable();
        $table->string('created_by_id', 26);
        $table->string('status', 30)->default('open');
        $table->boolean('is_offline_synced')->default(false);
        $table->timestamp('synced_at')->nullable();
        $table->timestamp('reported_at')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('client_started_at')->nullable();
        $table->timestamp('resolved_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('ticket_status_logs', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('changed_by_id', 26)->nullable();
        $table->string('old_status', 30)->nullable();
        $table->string('new_status', 30);
        $table->timestamps();
    });

    Schema::create('ticket_accomplishments', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_id', 26);
        $table->string('accomplished_by_id', 26);
        $table->text('remarks');
        $table->timestamp('accomplished_at');
        $table->timestamp('client_accomplished_at')->nullable();
        $table->string('consumer_name')->nullable();
        $table->string('signature_path')->nullable();
        $table->string('status', 30)->default('pending');
        $table->boolean('is_offline_synced')->default(false);
        $table->timestamp('synced_at')->nullable();
        $table->string('idempotency_key', 64)->nullable();
        $table->string('approved_by_id', 26)->nullable();
        $table->string('rejected_by_id', 26)->nullable();
        $table->text('rejection_reason')->nullable();
        $table->timestamps();
    });

    Schema::create('accomplishment_photos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('accomplishment_id');
        $table->string('file_path');
        $table->string('file_name')->nullable();
        $table->unsignedInteger('file_size')->nullable();
        $table->string('mime_type', 50)->nullable();
        $table->timestamps();
    });

    Schema::create('idempotency_records', function (Blueprint $table) {
        $table->string('id', 26)->primary();
        $table->string('user_id', 26);
        $table->string('idempotency_key', 64);
        $table->string('endpoint_path', 255);
        $table->string('request_hash', 64);
        $table->string('status', 30)->default('in_progress');
        $table->unsignedInteger('response_code')->nullable();
        $table->json('response_body')->nullable();
        $table->timestamp('expires_at');
        $table->timestamps();
        $table->unique(['user_id', 'idempotency_key']);
    });

    // Populate role and user
    DB::table('account_roles')->insert([
        'id' => 4,
        'role_name' => 'Field Personnel',
        'slug_identifier' => 'field_personnel',
    ]);

    Gate::define('access-field_personnel', fn ($user) => true);
    Gate::define('start', fn ($user, $ticket) => true);
    Gate::define('accomplish', fn ($user, $ticket) => true);

    Storage::fake('public');
});

afterEach(function () {
    DB::purge('offline_test');
});

function createAssignedTicketAndUser(): array {
    $userId = (string) Str::ulid();
    $teamId = (string) Str::ulid();
    $ticketId = (string) Str::ulid();

    $user = User::forceCreate([
        'id' => $userId,
        'username' => 'lineman_' . Str::random(6),
        'first_name' => 'Ralph',
        'last_name' => 'Personnel',
        'contact' => '09123456789',
        'role_id' => 4,
        'password' => 'secret',
    ]);

    DB::table('teams')->insert([
        'id' => $teamId,
        'team_name' => 'Alpha Crew',
        'shift_start' => '08:00:00',
        'shift_end' => '17:00:00',
    ]);

    DB::table('team_members')->insert([
        'user_id' => $userId,
        'team_id' => $teamId,
        'team_role_id' => 1,
    ]);

    $ticket = Ticket::forceCreate([
        'id' => $ticketId,
        'ticket_number' => 'TKT-' . Str::random(8),
        'complaint_source' => 'phone_call',
        'barangay' => 'San Pedro',
        'created_by_id' => $userId,
        'team_id' => $teamId,
        'status' => TicketStatus::ASSIGNED,
        'reported_at' => now()->subHours(4),
        'created_at' => now()->subHours(4),
    ]);

    return [$user, $ticket];
}

test('start endpoint rejects client timestamp in the future', function () {
    [$user, $ticket] = createAssignedTicketAndUser();

    $futureTimestamp = now()->addHours(2)->toIso8601String();

    $response = $this->actingAs($user)
        ->patchJson("/api/tickets/{$ticket->id}/start", [], [
            'X-Client-Timestamp' => $futureTimestamp,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('client_timestamp');
});

test('start endpoint rejects client timestamp earlier than ticket creation', function () {
    [$user, $ticket] = createAssignedTicketAndUser();

    $pastTimestamp = now()->subHours(10)->toIso8601String();

    $response = $this->actingAs($user)
        ->patchJson("/api/tickets/{$ticket->id}/start", [], [
            'X-Client-Timestamp' => $pastTimestamp,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('client_timestamp');
});

test('start endpoint rejects malformed or short idempotency key', function () {
    [$user, $ticket] = createAssignedTicketAndUser();

    $response = $this->actingAs($user)
        ->patchJson("/api/tickets/{$ticket->id}/start", [], [
            'X-Idempotency-Key' => 'short-key',
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['success' => false]);
});

test('offline start transitions ticket with client timestamp and sets is_offline_synced', function () {
    [$user, $ticket] = createAssignedTicketAndUser();

    $offlineTimestamp = now()->subHours(2);
    $idempotencyKey = (string) Str::uuid();

    $response = $this->actingAs($user)
        ->patchJson("/api/tickets/{$ticket->id}/start", [], [
            'X-Idempotency-Key' => $idempotencyKey,
            'X-Client-Timestamp' => $offlineTimestamp->toIso8601String(),
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'status' => 'in_progress',
                'is_offline_synced' => true,
            ],
        ])
        ->assertHeader('X-Cache', 'MISS');

    $freshTicket = Ticket::find($ticket->id);
    expect($freshTicket->status)->toBe(TicketStatus::IN_PROGRESS)
        ->and($freshTicket->is_offline_synced)->toBeTrue()
        ->and($freshTicket->started_at->format('Y-m-d H:i'))->toBe($offlineTimestamp->format('Y-m-d H:i'));

    expect(IdempotencyRecord::where('idempotency_key', $idempotencyKey)->count())->toBe(1);
});

test('idempotent replay on start returns cached response without duplicate status logs', function () {
    [$user, $ticket] = createAssignedTicketAndUser();

    $idempotencyKey = (string) Str::uuid();
    $clientTimestamp = now()->subHour()->toIso8601String();

    // First request
    $firstResponse = $this->actingAs($user)
        ->patchJson("/api/tickets/{$ticket->id}/start", [], [
            'X-Idempotency-Key' => $idempotencyKey,
            'X-Client-Timestamp' => $clientTimestamp,
        ]);

    $firstResponse->assertStatus(200);

    $initialStatusLogsCount = DB::table('ticket_status_logs')->where('ticket_id', $ticket->id)->count();
    expect($initialStatusLogsCount)->toBe(1);

    // Replay request with identical key
    $secondResponse = $this->actingAs($user)
        ->patchJson("/api/tickets/{$ticket->id}/start", [], [
            'X-Idempotency-Key' => $idempotencyKey,
            'X-Client-Timestamp' => $clientTimestamp,
        ]);

    $secondResponse->assertStatus(200)
        ->assertHeader('X-Cache', 'HIT')
        ->assertHeader('X-Idempotent-Replayed', 'true');

    // Verify no duplicate status log was created
    $postReplayStatusLogsCount = DB::table('ticket_status_logs')->where('ticket_id', $ticket->id)->count();
    expect($postReplayStatusLogsCount)->toBe(1);
});

test('offline accomplish transitions ticket to resolved with evidence and sets is_offline_synced', function () {
    [$user, $ticket] = createAssignedTicketAndUser();
    $ticket->update(['status' => TicketStatus::IN_PROGRESS, 'started_at' => now()->subHours(2)]);

    $idempotencyKey = (string) Str::uuid();
    $offlineTimestamp = now()->subHour();

    $response = $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/accomplish", [
            'remarks' => 'Replaced blown transformer fuse.',
            'consumer_name' => 'Juan Dela Cruz',
            'signature' => UploadedFile::fake()->image('signature.png'),
            'photos' => [
                UploadedFile::fake()->image('photo1.jpg'),
                UploadedFile::fake()->image('photo2.jpg'),
            ],
        ], [
            'X-Idempotency-Key' => $idempotencyKey,
            'X-Client-Timestamp' => $offlineTimestamp->toIso8601String(),
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'status' => 'pending',
                'is_offline_synced' => true,
            ],
        ])
        ->assertHeader('X-Cache', 'MISS');

    $accomplishment = DB::table('ticket_accomplishments')->where('ticket_id', $ticket->id)->first();
    expect($accomplishment)->not->toBeNull()
        ->and((bool) $accomplishment->is_offline_synced)->toBeTrue()
        ->and($accomplishment->idempotency_key)->toBe($idempotencyKey);

    expect(DB::table('accomplishment_photos')->where('accomplishment_id', $accomplishment->id)->count())->toBe(2);
});

test('idempotent replay on accomplish returns cached response without duplicate records or photos', function () {
    [$user, $ticket] = createAssignedTicketAndUser();
    $ticket->update(['status' => TicketStatus::IN_PROGRESS, 'started_at' => now()->subHours(2)]);

    $idempotencyKey = (string) Str::uuid();
    $offlineTimestamp = now()->subHour()->toIso8601String();

    $payload = [
        'remarks' => 'Replaced blown transformer fuse.',
        'consumer_name' => 'Juan Dela Cruz',
        'signature' => UploadedFile::fake()->image('signature.png'),
        'photos' => [
            UploadedFile::fake()->image('photo1.jpg'),
        ],
    ];

    $headers = [
        'X-Idempotency-Key' => $idempotencyKey,
        'X-Client-Timestamp' => $offlineTimestamp,
    ];

    // First request
    $firstResponse = $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/accomplish", $payload, $headers);

    $firstResponse->assertStatus(201);

    expect(DB::table('ticket_accomplishments')->where('ticket_id', $ticket->id)->count())->toBe(1)
        ->and(DB::table('accomplishment_photos')->count())->toBe(1);

    // Replay with identical key
    $secondResponse = $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/accomplish", $payload, $headers);

    $secondResponse->assertStatus(201)
        ->assertHeader('X-Cache', 'HIT')
        ->assertHeader('X-Idempotent-Replayed', 'true');

    // Confirm no duplicate rows created
    expect(DB::table('ticket_accomplishments')->where('ticket_id', $ticket->id)->count())->toBe(1)
        ->and(DB::table('accomplishment_photos')->count())->toBe(1);
});
