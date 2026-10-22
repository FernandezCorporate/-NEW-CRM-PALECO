<?php

use App\Models\Consumer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->cwdOfficer = User::where('username', 'alliah')->first();
    $this->closedAt = now()->startOfMinute();
});

test('durations are formatted as minutes, hours and minutes, or days and hours', function () {
    expect(Ticket::formatDuration(0))->toBe('1 minute')
        ->and(Ticket::formatDuration(45))->toBe('45 minutes')
        ->and(Ticket::formatDuration(60))->toBe('1 hour')
        ->and(Ticket::formatDuration(135))->toBe('2 hours 15 minutes')
        ->and(Ticket::formatDuration(1440))->toBe('1 day')
        ->and(Ticket::formatDuration(3060))->toBe('2 days 3 hours');
});

test('a ticket closed through a verified report shows its resolution time from reported_at to closed_at', function () {
    $ticket = Ticket::factory()->closed()->create([
        'reported_at' => $this->closedAt->copy()->subDays(2)->subHours(3),
        'closed_at' => $this->closedAt,
        'is_force_closed' => false,
    ]);

    expect($ticket->getResolutionMinutes())->toBe(3060)
        ->and($ticket->getResolutionDuration())->toBe('2 days 3 hours');

    $this->actingAs($this->cwdOfficer)->get(route('cwd.tickets.show', $ticket))
        ->assertOk()->assertSee('Resolved in 2 days 3 hours');
});

test('resolution time falls back to created_at when reported_at is missing', function () {
    $ticket = Ticket::factory()->closed()->create([
        'reported_at' => null,
        'closed_at' => $this->closedAt,
        'is_force_closed' => false,
    ]);
    $ticket->forceFill(['created_at' => $this->closedAt->copy()->subHours(5)])->saveQuietly();

    expect($ticket->fresh()->getResolutionDuration())->toBe('5 hours');
});

test('force-closed, cancelled and unfinished tickets have no resolution time', function () {
    $forceClosed = Ticket::factory()->closed()->create([
        'reported_at' => $this->closedAt->copy()->subDay(),
        'closed_at' => $this->closedAt,
        'is_force_closed' => true,
        'closure_reason' => 'Emergency halt.',
    ]);
    $cancelled = Ticket::factory()->cancelled()->create();
    $open = Ticket::factory()->open()->create();

    expect($forceClosed->getResolutionDuration())->toBeNull()
        ->and($cancelled->getResolutionDuration())->toBeNull()
        ->and($open->getResolutionDuration())->toBeNull();

    $this->actingAs($this->cwdOfficer)->get(route('cwd.tickets.show', $forceClosed))
        ->assertOk()->assertDontSee('Resolved in');
});

test('the consumer profile shows the average resolution time of its normally closed tickets', function () {
    $consumer = Consumer::factory()->create();

    // 2 hours and 4 hours average to 3 hours; the force-closed and open tickets are left out
    Ticket::factory()->closed()->create([
        'consumer_id' => $consumer->id,
        'reported_at' => $this->closedAt->copy()->subHours(2),
        'closed_at' => $this->closedAt,
        'is_force_closed' => false,
    ]);
    Ticket::factory()->closed()->create([
        'consumer_id' => $consumer->id,
        'reported_at' => $this->closedAt->copy()->subHours(4),
        'closed_at' => $this->closedAt,
        'is_force_closed' => false,
    ]);
    Ticket::factory()->closed()->create([
        'consumer_id' => $consumer->id,
        'reported_at' => $this->closedAt->copy()->subDays(10),
        'closed_at' => $this->closedAt,
        'is_force_closed' => true,
        'closure_reason' => 'Emergency halt.',
    ]);
    Ticket::factory()->open()->create(['consumer_id' => $consumer->id]);

    $this->actingAs($this->cwdOfficer)->get(route('cwd.consumers.show', $consumer))
        ->assertOk()
        ->assertViewHas('averageResolution', '3 hours')
        ->assertViewHas('resolvedTicketCount', 2)
        ->assertSee('Average Resolution Time')
        ->assertSee('Across 2 tickets closed through a verified report')
        ->assertSee('2 hours')
        ->assertSee('4 hours');
});

test('a consumer without normally closed tickets shows no average yet', function () {
    $consumer = Consumer::factory()->create();
    Ticket::factory()->open()->create(['consumer_id' => $consumer->id]);

    $this->actingAs($this->cwdOfficer)->get(route('cwd.consumers.show', $consumer))
        ->assertOk()
        ->assertViewHas('averageResolution', null)
        ->assertSee('No resolved tickets yet');
});
