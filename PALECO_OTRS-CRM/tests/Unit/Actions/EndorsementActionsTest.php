<?php

use App\Actions\Endorsements\DecideEndorsement;
use App\Actions\Endorsements\InitiateEndorsement;
use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketEndorsement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('InitiateEndorsement freezes ticket into pending endorsement state', function () {
    $dept1 = Department::first();
    $dept2 = Department::factory()->create();
    $supervisor = User::factory()->supervisor()->create(['department_id' => $dept1->id]);

    $ticket = Ticket::factory()->create([
        'department_id' => $dept1->id,
        'status' => TicketStatus::IN_PROGRESS->value,
    ]);

    $action = app(InitiateEndorsement::class);
    $endorsement = $action->execute($ticket, [
        'suggested_department_id' => $dept2->id,
        'reason' => 'Tree branch entanglement requires clearance by forestry operations.',
    ], $supervisor);

    expect($endorsement)->toBeInstanceOf(TicketEndorsement::class);
    expect($endorsement->status)->toBe(EndorsementStatus::PENDING);
    expect($endorsement->suggested_department_id)->toBe($dept2->id);
    expect($ticket->fresh()->status)->toBe(TicketStatus::PENDING_ENDORSEMENT);
});

test('InitiateEndorsement prevents duplicate pending endorsements', function () {
    $dept1 = Department::first();
    $dept2 = Department::factory()->create();
    $supervisor = User::factory()->supervisor()->create(['department_id' => $dept1->id]);

    $ticket = Ticket::factory()->create([
        'department_id' => $dept1->id,
        'status' => TicketStatus::IN_PROGRESS->value,
    ]);

    $action = app(InitiateEndorsement::class);
    $action->execute($ticket, [
        'suggested_department_id' => $dept2->id,
        'reason' => 'First endorsement reason.',
    ], $supervisor);

    expect(fn () => $action->execute($ticket->fresh(), [
        'suggested_department_id' => $dept2->id,
        'reason' => 'Second duplicate endorsement attempt.',
    ], $supervisor))->toThrow(ValidationException::class);
});

test('InitiateEndorsement refuses child tickets', function () {
    $dept1 = Department::first();
    $dept2 = Department::factory()->create();
    $supervisor = User::factory()->supervisor()->create(['department_id' => $dept1->id]);

    $parent = Ticket::factory()->create(['department_id' => $dept1->id]);
    $child = Ticket::factory()->create([
        'parent_ticket_id' => $parent->id,
        'ticket_number' => $parent->ticket_number.'-1',
        'department_id' => $dept1->id,
        'status' => TicketStatus::IN_PROGRESS->value,
    ]);

    $action = app(InitiateEndorsement::class);

    expect(fn () => $action->execute($child, [
        'suggested_department_id' => $dept2->id,
        'reason' => 'Attempting to endorse a child ticket.',
    ], $supervisor))->toThrow(ValidationException::class);

    expect($child->fresh()->status)->toBe(TicketStatus::IN_PROGRESS);
});

test('DecideEndorsement approval approves endorsement and generates child ticket', function () {
    $dept1 = Department::first();
    $dept2 = Department::factory()->create();
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $supervisor = User::factory()->supervisor()->create(['department_id' => $dept1->id]);

    $this->actingAs($cwdOfficer);

    $ticket = Ticket::factory()->create([
        'department_id' => $dept1->id,
        'status' => TicketStatus::IN_PROGRESS->value,
    ]);

    $endorsement = app(InitiateEndorsement::class)->execute($ticket, [
        'suggested_department_id' => $dept2->id,
        'reason' => 'Civil engineering road excavation required.',
    ], $supervisor);

    $decideAction = app(DecideEndorsement::class);
    $result = $decideAction->execute([
        'status' => EndorsementStatus::APPROVED->value,
        'department_id' => $dept2->id,
    ], $endorsement);

    expect($result['success'])->toBeTrue();
    expect($endorsement->fresh()->status)->toBe(EndorsementStatus::APPROVED);
    expect($ticket->fresh()->status)->toBe(TicketStatus::ENDORSED);

    $childTicket = Ticket::where('parent_ticket_id', $ticket->id)->first();
    expect($childTicket)->not->toBeNull();
    expect($childTicket->department_id)->toBe($dept2->id);
    expect($childTicket->status)->toBe(TicketStatus::OPEN);
});

test('DecideEndorsement rejection restores parent ticket to previous active status', function () {
    $dept1 = Department::first();
    $dept2 = Department::factory()->create();
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $team = Team::factory()->create(['department_id' => $dept1->id]);
    $supervisor = User::factory()->supervisor()->create(['department_id' => $dept1->id]);

    $this->actingAs($cwdOfficer);

    $ticket = Ticket::factory()->create([
        'department_id' => $dept1->id,
        'team_id' => $team->id,
        'status' => TicketStatus::IN_PROGRESS->value,
    ]);

    $endorsement = app(InitiateEndorsement::class)->execute($ticket, [
        'suggested_department_id' => $dept2->id,
        'reason' => 'Requesting secondary bucket truck.',
    ], $supervisor);

    $decideAction = app(DecideEndorsement::class);
    $result = $decideAction->execute([
        'status' => EndorsementStatus::REJECTED->value,
        'rejection_reason' => 'Existing assigned crew possesses appropriate telescoping hot stick.',
    ], $endorsement);

    expect($result['success'])->toBeTrue();
    expect($endorsement->fresh()->status)->toBe(EndorsementStatus::REJECTED);
    expect($endorsement->fresh()->rejection_reason)->toBe('Existing assigned crew possesses appropriate telescoping hot stick.');
    expect($ticket->fresh()->status)->toBe(TicketStatus::IN_PROGRESS);
});
