<?php

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->personnel = User::where('username', 'ralph')->first();
    $this->dept = Department::where('dept_name', 'like', '%Technical Services%')->first() ?? Department::first();

    // Ensure supervisor belongs to this department
    $this->supervisor->update(['department_id' => $this->dept->id]);

    $this->team1 = Team::where('department_id', $this->dept->id)->first();
    $this->category = TicketCategory::first();

    $this->ticket = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);
});

test('supervisor can view assign options for open ticket', function () {
    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->getJson("/api/v1/tickets/{$this->ticket->id}/assign-options");

    $response->assertOk();
    $response->assertJsonStructure([
        'success',
        'data' => [
            '*' => [
                'id',
                'team_name',
                'shift_start',
            ],
        ],
    ]);
});

test('field personnel cannot view assign options', function () {
    Sanctum::actingAs($this->personnel, ['*']);

    $response = $this->getJson("/api/v1/tickets/{$this->ticket->id}/assign-options");

    // Supervisor gate returns 404
    $response->assertNotFound();
});

test('supervisor can assign open ticket to field team', function () {
    Sanctum::actingAs($this->supervisor, ['*']);

    $payload = [
        'team_id' => $this->team1->id,
        'reason' => 'First responder dispatch for technical outage.',
    ];

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/assign", $payload);

    $response->assertOk();
    $response->assertJsonPath('success', true);

    $this->ticket->refresh();
    expect($this->ticket->status)->toBe(TicketStatus::ASSIGNED)
        ->and($this->ticket->team_id)->toBe($this->team1->id);

    // Verify assignment record
    $assignment = TicketAssignment::where('ticket_id', $this->ticket->id)->first();
    expect($assignment)->not->toBeNull()
        ->and($assignment->team_id)->toBe($this->team1->id)
        ->and($assignment->assigned_by_id)->toBe($this->supervisor->id)
        ->and($assignment->unassigned_at)->toBeNull();
});

test('supervisor can reassign ticket to another team and close previous assignment', function () {
    Sanctum::actingAs($this->supervisor, ['*']);

    // Create a second team in the same department
    $team2 = Team::factory()->create([
        'department_id' => $this->dept->id,
        'team_name' => 'Secondary Crew',
    ]);

    // Initial assignment
    $this->postJson("/api/v1/tickets/{$this->ticket->id}/assign", [
        'team_id' => $this->team1->id,
        'reason' => 'Initial dispatch',
    ])->assertOk();

    $firstAssignment = TicketAssignment::where('ticket_id', $this->ticket->id)
        ->where('team_id', $this->team1->id)
        ->first();

    // Reassignment
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/assign", [
        'team_id' => $team2->id,
        'reason' => 'Heavy equipment required - transferring to specialized unit',
    ]);

    $response->assertOk();

    $this->ticket->refresh();
    expect($this->ticket->team_id)->toBe($team2->id);

    // Previous assignment unassigned_at should now be set
    expect($firstAssignment->fresh()->unassigned_at)->not->toBeNull();

    // New assignment active
    $newAssignment = TicketAssignment::where('ticket_id', $this->ticket->id)
        ->where('team_id', $team2->id)
        ->first();
    expect($newAssignment->unassigned_at)->toBeNull();
});

test('assigning ticket to a team in another department fails validation', function () {
    Sanctum::actingAs($this->supervisor, ['*']);

    $otherDept = Department::where('id', '!=', $this->dept->id)->first() ?? Department::factory()->create();
    $foreignTeam = Team::factory()->create([
        'department_id' => $otherDept->id,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/assign", [
        'team_id' => $foreignTeam->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['team_id']);
});

test('cannot assign tickets that are in-progress or closed', function (string $stateMethod) {
    Sanctum::actingAs($this->supervisor, ['*']);

    $ticket = Ticket::factory()->$stateMethod($this->team1)->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'team_id' => $this->team1->id,
        'reason' => 'Testing block on progressed ticket',
    ]);

    // Policy assign checks in_array(ticket->status, [IN_PROGRESS, RESOLVED, CLOSED]) -> deny
    $response->assertForbidden();
})->with(['inProgress', 'closed']);

test('cannot assign a ticket pending endorsement', function () {
    Sanctum::actingAs($this->supervisor, ['*']);

    $ticket = Ticket::factory()->pendingEndorsement()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'team_id' => $this->team1->id,
        'reason' => 'Testing block on pending endorsement ticket',
    ]);

    $response->assertForbidden();
});
