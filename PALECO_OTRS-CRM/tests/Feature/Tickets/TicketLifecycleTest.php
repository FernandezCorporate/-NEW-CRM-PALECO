<?php

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

