<?php

use App\Actions\Tickets\CancelTicket;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->personnel = User::where('username', 'ralph')->first();
    $this->cwdOfficer = User::where('username', 'alliah')->first();
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->admin = User::where('username', 'allenglenn')->first();

    $this->dept = Department::first();
    $this->team = Team::first();
    $this->category = TicketCategory::first();

    // Ensure personnel is in the team
    if (! $this->team->members->contains($this->personnel->id)) {
        $this->team->members()->attach($this->personnel->id, ['team_role_id' => 1]);
    }
});

test('field personnel can start work on an assigned ticket', function () {
    Sanctum::actingAs($this->personnel, ['*']);

    $ticket = Ticket::factory()->assigned($this->team)->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->withHeaders([
        'X-Idempotency-Key' => 'start-test-1111-2222-3333',
    ])->patchJson("/api/v1/tickets/{$ticket->id}/start");

    $response->assertOk();
    $response->assertJsonPath('success', true);

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::IN_PROGRESS)
        ->and($ticket->started_at)->not->toBeNull()
        ->and($ticket->is_offline_synced)->toBeFalse();

    // Status log created
    expect($ticket->statusLog()->count())->toBe(1)
        ->and($ticket->statusLog()->first()->new_status)->toBe(TicketStatus::IN_PROGRESS);
});

test('starting an already in-progress ticket is idempotent and succeeds without error', function () {
    Sanctum::actingAs($this->personnel, ['*']);

    $ticket = Ticket::factory()->inProgress($this->team)->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->withHeaders([
        'X-Idempotency-Key' => 'start-test-idempotent-key-1234',
    ])->patchJson("/api/v1/tickets/{$ticket->id}/start");

    $response->assertOk();
    expect($ticket->fresh()->status)->toBe(TicketStatus::IN_PROGRESS);
});

test('cannot start a ticket that is not assigned to user team', function () {
    Sanctum::actingAs($this->personnel, ['*']);

    $ticket = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->withHeaders([
        'X-Idempotency-Key' => 'start-test-open-ticket-key',
    ])->patchJson("/api/v1/tickets/{$ticket->id}/start");

    // Policy blocks with 404 because team_id is null / not worker's team
    $response->assertNotFound();
});

test('cannot start a ticket that has already progressed past assigned status', function () {
    Sanctum::actingAs($this->personnel, ['*']);

    $ticket = Ticket::factory()->resolved($this->team)->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->withHeaders([
        'X-Idempotency-Key' => 'start-test-resolved-ticket-key',
    ])->patchJson("/api/v1/tickets/{$ticket->id}/start");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
});

test('cwd officer can cancel an open ticket with reason', function () {
    $ticket = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.cancel', ['ticket' => $ticket]), [
            'cancellation_reason' => 'Consumer resolved the breaker trip internally before dispatch.',
        ]);

    $response->assertRedirect(route('cwd.tickets.show', ['ticket' => $ticket]));
    $response->assertSessionHas('success');

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::CANCELLED)
        ->and($ticket->cancelled_at)->not->toBeNull()
        ->and($ticket->cancellation_reason)->toContain('Consumer resolved');
});

test('cannot cancel an assigned or in-progress ticket', function () {
    $ticket = Ticket::factory()->inProgress($this->team)->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.cancel', ['ticket' => $ticket]), [
            'cancellation_reason' => 'Invalid late cancellation attempt.',
        ]);

    // Policy cancel rejects with 403
    $response->assertForbidden();
    expect($ticket->fresh()->status)->toBe(TicketStatus::IN_PROGRESS);
});

test('cwd officer can force-close an assigned or in-progress ticket with reason', function () {
    $ticket = Ticket::factory()->inProgress($this->team)->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.forceClose', ['ticket' => $ticket]), [
            'closure_reason' => 'Site inaccessible due to local floodwaters; rescheduled to tomorrow maintenance queue.',
        ]);

    $response->assertRedirect(route('cwd.tickets.show', ['ticket' => $ticket]));
    $response->assertSessionHas('success');

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::CLOSED)
        ->and($ticket->is_force_closed)->toBeTrue()
        ->and($ticket->closed_by_id)->toBe($this->cwdOfficer->id)
        ->and($ticket->closure_reason)->toContain('Site inaccessible');
});

test('force-closing is rejected if open tickets try to use it instead of cancellation', function () {
    $ticket = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.forceClose', ['ticket' => $ticket]), [
            'closure_reason' => 'Attempting force-close on open ticket.',
        ]);

    // Policy forceClose rejects open tickets with 403
    $response->assertForbidden();
    expect($ticket->fresh()->status)->toBe(TicketStatus::OPEN);
});

test('force-closing is rejected for resolved tickets awaiting supervisor verification', function () {
    $ticket = Ticket::factory()->resolved($this->team)->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.forceClose', ['ticket' => $ticket]), [
            'closure_reason' => 'Attempting force-close while report awaits verification.',
        ]);

    // Policy forceClose rejects resolved tickets with 403
    $response->assertForbidden();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::RESOLVED)
        ->and($ticket->is_force_closed)->toBeFalse();
});

test('force-closing parent ticket is blocked when active child tickets exist', function () {
    $parent = Ticket::factory()->inProgress($this->team)->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);

    // Active child ticket
    Ticket::factory()->open()->create([
        'parent_ticket_id' => $parent->id,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.forceClose', ['ticket' => $parent]), [
            'closure_reason' => 'Force close while child is unresolved.',
        ]);

    $response->assertRedirect(route('cwd.tickets.show', ['ticket' => $parent]));
    $response->assertSessionHas('error');
    expect($parent->fresh()->status)->toBe(TicketStatus::IN_PROGRESS);
});


test('cwd officer cannot cancel a parent while a child ticket is not cancelled', function (string $childState) {
    $parent = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);
    Ticket::factory()->{$childState}()->create([
        'parent_ticket_id' => $parent->id,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);

    $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.cancel', ['ticket' => $parent]), [
            'cancellation_reason' => 'Duplicate of an earlier report.',
        ])
        ->assertForbidden();

    expect($parent->fresh()->status)->toBe(TicketStatus::OPEN);
})->with([
    'open child' => ['open'],
    'assigned child' => ['assigned'],
    'in progress child' => ['inProgress'],
    'closed child' => ['closed'],
]);

test('cwd officer can cancel a parent whose child tickets are all cancelled', function () {
    $parent = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);
    Ticket::factory()->cancelled()->create([
        'parent_ticket_id' => $parent->id,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);

    $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.cancel', ['ticket' => $parent]), [
            'cancellation_reason' => 'Duplicate of an earlier report.',
        ])
        ->assertSessionHas('success');

    expect($parent->fresh()->status)->toBe(TicketStatus::CANCELLED);
});

test('CancelTicket lists the child tickets that block cancelling the parent', function () {
    $parent = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);
    $child = Ticket::factory()->assigned($this->team)->create([
        'parent_ticket_id' => $parent->id,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);

    try {
        app(CancelTicket::class)->execute($parent, ['cancellation_reason' => 'Duplicate.'], $this->cwdOfficer);
        $this->fail('Expected the cancellation to be refused.');
    } catch (ValidationException $e) {
        expect($e->errors()['child_tickets'][0])->toContain("{$child->ticket_number} (".TicketStatus::ASSIGNED->label().')');
    }

    expect($parent->fresh()->status)->toBe(TicketStatus::OPEN)
        ->and($child->fresh()->status)->toBe(TicketStatus::ASSIGNED);
});
