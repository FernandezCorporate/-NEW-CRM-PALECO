<?php

use App\Actions\Accomplishments\SubmitAccomplishment;
use App\Actions\Accomplishments\VerifyAccomplishment;
use App\Enums\TicketAccomplishmentStatus;
use App\Enums\TicketStatus;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('public');
});

test('SubmitAccomplishment resolves ticket and stores report with signature and photos', function () {
    $team = Team::factory()->create();
    $worker = User::factory()->fieldPersonnel()->create(['department_id' => $team->department_id]);
    $teamRole = TeamRole::first();
    $team->members()->attach($worker->id, ['team_role_id' => $teamRole->id]);

    $ticket = Ticket::factory()->create([
        'team_id' => $team->id,
        'department_id' => $team->department_id,
        'status' => TicketStatus::IN_PROGRESS->value,
    ]);

    $action = app(SubmitAccomplishment::class);
    $data = [
        'remarks' => 'Replaced burnt fuse cutout and restored three-phase power.',
        'signature' => UploadedFile::fake()->image('signature.png'),
        'photos' => [
            UploadedFile::fake()->image('before.jpg'),
            UploadedFile::fake()->image('after.jpg'),
        ],
    ];

    $accomplishment = $action->execute($ticket, $worker, $data);

    expect($accomplishment)->toBeInstanceOf(TicketAccomplishment::class);
    expect($accomplishment->status)->toBe(TicketAccomplishmentStatus::PENDING);
    expect($ticket->fresh()->status)->toBe(TicketStatus::RESOLVED);
    expect($accomplishment->photos)->toHaveCount(2);
});

test('SubmitAccomplishment respects idempotency key', function () {
    $team = Team::factory()->create();
    $worker = User::factory()->fieldPersonnel()->create(['department_id' => $team->department_id]);
    $teamRole = TeamRole::first();
    $team->members()->attach($worker->id, ['team_role_id' => $teamRole->id]);

    $ticket = Ticket::factory()->create([
        'team_id' => $team->id,
        'department_id' => $team->department_id,
        'status' => TicketStatus::IN_PROGRESS->value,
    ]);

    $action = app(SubmitAccomplishment::class);
    $data = [
        'remarks' => 'Initial resolution description.',
        'signature' => UploadedFile::fake()->image('signature.png'),
        'photos' => [UploadedFile::fake()->image('photo.jpg')],
    ];

    $key = 'idemp-key-unit-test-12345';
    $first = $action->execute($ticket, $worker, $data, null, $key);
    $second = $action->execute($ticket->fresh(), $worker, $data, null, $key);

    expect($first->id)->toBe($second->id);
    expect(TicketAccomplishment::where('ticket_id', $ticket->id)->count())->toBe(1);
});

test('VerifyAccomplishment approves report, closes ticket, and finalizes assignment', function () {
    $supervisor = User::factory()->supervisor()->create();
    $team = Team::factory()->create(['department_id' => $supervisor->department_id]);
    $ticket = Ticket::factory()->create([
        'department_id' => $supervisor->department_id,
        'team_id' => $team->id,
        'status' => TicketStatus::RESOLVED->value,
    ]);
    $accomplishment = TicketAccomplishment::factory()->create([
        'ticket_id' => $ticket->id,
        'status' => TicketAccomplishmentStatus::PENDING->value,
    ]);

    $action = app(VerifyAccomplishment::class);
    $evaluated = $action->execute($ticket, $accomplishment, [
        'status' => TicketAccomplishmentStatus::APPROVED->value,
    ], $supervisor);

    expect($evaluated->status)->toBe(TicketAccomplishmentStatus::APPROVED);
    expect($evaluated->approved_by_id)->toBe($supervisor->id);
    expect($ticket->fresh()->status)->toBe(TicketStatus::CLOSED);
});

test('VerifyAccomplishment rejects report, reverts ticket to in-progress with reason', function () {
    $supervisor = User::factory()->supervisor()->create();
    $team = Team::factory()->create(['department_id' => $supervisor->department_id]);
    $ticket = Ticket::factory()->create([
        'department_id' => $supervisor->department_id,
        'team_id' => $team->id,
        'status' => TicketStatus::RESOLVED->value,
    ]);
    $accomplishment = TicketAccomplishment::factory()->create([
        'ticket_id' => $ticket->id,
        'status' => TicketAccomplishmentStatus::PENDING->value,
    ]);

    $action = app(VerifyAccomplishment::class);
    $evaluated = $action->execute($ticket, $accomplishment, [
        'status' => TicketAccomplishmentStatus::REJECTED->value,
        'rejection_reason' => 'Clear photo evidence of completed meter grounding is missing.',
    ], $supervisor);

    expect($evaluated->status)->toBe(TicketAccomplishmentStatus::REJECTED);
    expect($evaluated->rejection_reason)->toBe('Clear photo evidence of completed meter grounding is missing.');
    expect($evaluated->rejected_by_id)->toBe($supervisor->id);
    expect($ticket->fresh()->status)->toBe(TicketStatus::IN_PROGRESS);
});

test('VerifyAccomplishment throws validation exception if report already evaluated', function () {
    $supervisor = User::factory()->supervisor()->create();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::CLOSED->value]);
    $accomplishment = TicketAccomplishment::factory()->approved()->create(['ticket_id' => $ticket->id]);

    $action = app(VerifyAccomplishment::class);

    expect(fn () => $action->execute($ticket, $accomplishment, [
        'status' => TicketAccomplishmentStatus::APPROVED->value,
    ], $supervisor))->toThrow(ValidationException::class);
});
