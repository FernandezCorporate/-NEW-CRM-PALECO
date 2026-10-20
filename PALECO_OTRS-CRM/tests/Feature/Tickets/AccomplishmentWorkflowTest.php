<?php

use App\Enums\TicketAccomplishmentStatus;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->personnel = User::where('username', 'ralph')->first();
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->cwdOfficer = User::where('username', 'alliah')->first();

    $this->dept = Department::first();
    $this->supervisor->update(['department_id' => $this->dept->id]);

    $this->team = Team::where('department_id', $this->dept->id)->first() ?? Team::first();
    $this->category = TicketCategory::first();

    if (! $this->team->members->contains($this->personnel->id)) {
        $this->team->members()->attach($this->personnel->id, ['team_role_id' => 1]);
    }

    $this->ticket = Ticket::factory()->inProgress($this->team)->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);
});

test('field personnel can submit accomplishment report with proof of work photo and signature', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->personnel, ['*']);

    $photo = UploadedFile::fake()->image('restoration_proof.jpg', 600, 400);
    $signature = UploadedFile::fake()->image('signature.png', 300, 150);

    $payload = [
        'remarks' => 'Replaced blown transformer fuse cutout and restored three-phase power.',
        'consumer_name' => 'Maria Santos',
        'photos' => [$photo],
        'signature' => $signature,
    ];

    $response = $this->withHeaders([
        'X-Idempotency-Key' => 'accomplish-uuid-1111-2222-3333',
    ])->postJson("/api/v1/tickets/{$this->ticket->id}/accomplish", $payload);

    $response->assertCreated();
    $response->assertJsonPath('success', true);

    $this->ticket->refresh();
    expect($this->ticket->status)->toBe(TicketStatus::RESOLVED);

    $accomplishment = TicketAccomplishment::where('ticket_id', $this->ticket->id)->first();
    expect($accomplishment)->not->toBeNull()
        ->and($accomplishment->status)->toBe(TicketAccomplishmentStatus::PENDING)
        ->and($accomplishment->accomplished_by_id)->toBe($this->personnel->id)
        ->and($accomplishment->photos()->count())->toBe(1);
});

test('submitting accomplishment with same idempotency key returns cached report without duplicate rows', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->personnel, ['*']);

    $key = 'idempotent-accomplish-unique-key-999';

    $payload = [
        'remarks' => 'Initial submission.',
        'consumer_name' => 'John Doe',
        'photos' => [UploadedFile::fake()->image('proof.jpg')],
        'signature' => UploadedFile::fake()->image('signature.png'),
    ];

    $res1 = $this->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson("/api/v1/tickets/{$this->ticket->id}/accomplish", $payload);
    $res1->assertCreated();

    $countBefore = TicketAccomplishment::count();

    // Replay submission
    $res2 = $this->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson("/api/v1/tickets/{$this->ticket->id}/accomplish", $payload);

    expect(TicketAccomplishment::count())->toBe($countBefore);
});

test('supervisor can retrieve accomplishments list for ticket', function () {
    $accomplishment = TicketAccomplishment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->personnel->id,
    ]);

    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->getJson("/api/v1/tickets/{$this->ticket->id}/accomplishments");

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($accomplishment->id);
});

test('supervisor can view single accomplishment details', function () {
    $accomplishment = TicketAccomplishment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->personnel->id,
    ]);

    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->getJson("/api/v1/tickets/{$this->ticket->id}/accomplishments/{$accomplishment->id}");

    $response->assertOk();
    $response->assertJsonPath('data.id', $accomplishment->id);
});

test('supervisor can approve accomplishment report and close ticket', function () {
    $this->ticket->update(['status' => TicketStatus::RESOLVED]);

    $accomplishment = TicketAccomplishment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->personnel->id,
        'status' => TicketAccomplishmentStatus::PENDING,
    ]);

    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/accomplishments/{$accomplishment->id}/verify", [
        'status' => 'approved',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);

    $accomplishment->refresh();
    expect($accomplishment->status)->toBe(TicketAccomplishmentStatus::APPROVED)
        ->and($accomplishment->approved_by_id)->toBe($this->supervisor->id);

    $this->ticket->refresh();
    expect($this->ticket->status)->toBe(TicketStatus::CLOSED)
        ->and($this->ticket->closed_at)->not->toBeNull();
});

test('supervisor can reject accomplishment report reverting ticket to in-progress', function () {
    $this->ticket->update(['status' => TicketStatus::RESOLVED]);

    $accomplishment = TicketAccomplishment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->personnel->id,
        'status' => TicketAccomplishmentStatus::PENDING,
    ]);

    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/accomplishments/{$accomplishment->id}/verify", [
        'status' => 'rejected',
        'rejection_reason' => 'Clearer photographic evidence of repair needed.',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);

    $accomplishment->refresh();
    expect($accomplishment->status)->toBe(TicketAccomplishmentStatus::REJECTED)
        ->and($accomplishment->rejected_by_id)->toBe($this->supervisor->id)
        ->and($accomplishment->rejection_reason)->toContain('Clearer photographic evidence');

    $this->ticket->refresh();
    expect($this->ticket->status)->toBe(TicketStatus::IN_PROGRESS)
        ->and($this->ticket->resolved_at)->toBeNull();
});

test('rejecting accomplishment report requires rejection_reason', function () {
    $accomplishment = TicketAccomplishment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->personnel->id,
        'status' => TicketAccomplishmentStatus::PENDING,
    ]);

    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/accomplishments/{$accomplishment->id}/verify", [
        'status' => 'rejected',
        'rejection_reason' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['rejection_reason']);
});

test('evaluating an already evaluated accomplishment report is rejected', function () {
    $accomplishment = TicketAccomplishment::factory()->approved($this->supervisor)->create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->personnel->id,
    ]);

    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/accomplishments/{$accomplishment->id}/verify", [
        'status' => 'approved',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
});

test('pending report on a force-closed ticket cannot be evaluated', function (string $decision) {
    $closedAt = now()->subHour()->startOfSecond();

    // Legacy state: ticket force-closed while its accomplishment report was still pending
    $this->ticket->update([
        'status' => TicketStatus::CLOSED,
        'is_force_closed' => true,
        'closure_reason' => 'Force-closed by CWD before verification.',
        'closed_by_id' => $this->cwdOfficer->id,
        'closed_at' => $closedAt,
    ]);

    $accomplishment = TicketAccomplishment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->personnel->id,
        'status' => TicketAccomplishmentStatus::PENDING,
    ]);

    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/accomplishments/{$accomplishment->id}/verify", [
        'status' => $decision,
        'rejection_reason' => 'Attempting to evaluate after force-closure.',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);

    $this->ticket->refresh();
    expect($this->ticket->status)->toBe(TicketStatus::CLOSED)
        ->and($this->ticket->is_force_closed)->toBeTrue()
        ->and($this->ticket->closed_at->equalTo($closedAt))->toBeTrue();

    expect($accomplishment->fresh()->status)->toBe(TicketAccomplishmentStatus::PENDING);
})->with(['approved', 'rejected']);

test('cwd officer can inspect accomplishment report in web portal', function () {
    $accomplishment = TicketAccomplishment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->personnel->id,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->get(route('cwd.tickets.accomplishments.show', [
            'ticket' => $this->ticket,
            'accomplishment' => $accomplishment,
        ]));

    $response->assertOk();
    $response->assertViewIs('cwd.pages.ticketAccomplishmentDetails');
    $response->assertViewHas('accomplishment');
});

test('a stale resubmission after the worker report was rejected returns 422 instead of the old report', function () {
    Storage::fake('public');
    $otherWorker = User::factory()->fieldPersonnel()->create();
    $this->team->members()->attach($otherWorker->id, ['team_role_id' => 1]);

    // Crew member A's report was rejected, then crew member B redid the work and submitted
    TicketAccomplishment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->personnel->id,
        'status' => TicketAccomplishmentStatus::REJECTED,
    ]);
    TicketAccomplishment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $otherWorker->id,
        'status' => TicketAccomplishmentStatus::PENDING,
    ]);
    $this->ticket->update(['status' => TicketStatus::RESOLVED]);
    $countBefore = TicketAccomplishment::count();

    Sanctum::actingAs($this->personnel, ['*']);

    $this->withHeaders(['X-Idempotency-Key' => 'queued-offline-resubmission'])
        ->postJson("/api/v1/tickets/{$this->ticket->id}/accomplish", [
            'remarks' => 'Queued while offline.',
            'consumer_name' => 'Maria Santos',
            'photos' => [UploadedFile::fake()->image('new_proof.jpg')],
            'signature' => UploadedFile::fake()->image('signature.png'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status' => 'This ticket already has a pending accomplishment report under supervisor review.']);

    expect(TicketAccomplishment::count())->toBe($countBefore)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('an honest retry while the worker report is still pending returns that report', function () {
    Storage::fake('public');
    $pending = TicketAccomplishment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'accomplished_by_id' => $this->personnel->id,
        'status' => TicketAccomplishmentStatus::PENDING,
    ]);
    $this->ticket->update(['status' => TicketStatus::RESOLVED]);

    Sanctum::actingAs($this->personnel, ['*']);

    $this->withHeaders(['X-Idempotency-Key' => 'retry-with-a-new-key'])
        ->postJson("/api/v1/tickets/{$this->ticket->id}/accomplish", [
            'remarks' => 'Retry after a timeout.',
            'consumer_name' => 'Maria Santos',
            'photos' => [UploadedFile::fake()->image('proof.jpg')],
            'signature' => UploadedFile::fake()->image('signature.png'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.id', $pending->id);

    expect(TicketAccomplishment::where('ticket_id', $this->ticket->id)->count())->toBe(1);
});
