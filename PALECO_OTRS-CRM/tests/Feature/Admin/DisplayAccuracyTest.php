<?php

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketEndorsement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
    $this->cwd = User::where('username', 'alliah')->first();
    $this->admin = User::where('username', 'allenglenn')->first();
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->dept = Department::find($this->supervisor->department_id);
    $this->team = Team::where('department_id', $this->dept->id)->first();
});

// --- M2: stale intake edits ---

test('a stale intake edit is refused and keeps the other officer\'s change', function () {
    $ticket = Ticket::factory()->open()->create(['department_id' => $this->dept->id, 'purok' => 'Original']);
    $payload = [
        'original_updated_at' => $ticket->updated_at->toIso8601String(),
        'consumer_contact' => '09123456789', 'complaint_source' => 'phone_call', 'complaint_description' => 'No power in the area',
        'street' => 'Rizal Street', 'barangay' => 'Poblacion', 'department_id' => $this->dept->id,
        'other_category' => false, 'category_id' => TicketCategory::first()->id, 'link_consumer' => false,
    ];

    // Timestamps have one-second precision, so the first save must land in a later second than the ticket's creation
    $this->travel(2)->seconds();
    $this->actingAs($this->cwd)->put(route('cwd.tickets.update', $ticket), $payload + ['purok' => 'First edit'])->assertSessionHas('success');

    $this->actingAs($this->cwd)
        ->from(route('cwd.tickets.editForm', $ticket))
        ->put(route('cwd.tickets.update', $ticket), $payload + ['purok' => 'Stale edit'])
        ->assertRedirect(route('cwd.tickets.editForm', $ticket))
        ->assertSessionHas('error', fn ($message) => str_starts_with($message, 'Conflict: This ticket was modified by another officer'));

    expect($ticket->fresh()->purok)->toBe('First edit');
});

// --- D1 and D5: mobile counts ---

test('assign options count each team\'s assigned and in-progress tickets', function () {
    Ticket::factory()->create(['department_id' => $this->dept->id, 'team_id' => $this->team->id, 'status' => TicketStatus::ASSIGNED->value]);
    Ticket::factory()->create(['department_id' => $this->dept->id, 'team_id' => $this->team->id, 'status' => TicketStatus::IN_PROGRESS->value]);
    Ticket::factory()->create(['department_id' => $this->dept->id, 'team_id' => $this->team->id, 'status' => TicketStatus::CLOSED->value]);
    $open = Ticket::factory()->open()->create(['department_id' => $this->dept->id]);

    Sanctum::actingAs($this->supervisor, ['*']);
    $row = collect($this->getJson("/api/v1/tickets/{$open->id}/assign-options")->assertOk()->json('data'))->firstWhere('id', $this->team->id);

    expect($row['tickets'])->toBe(2);
});

test('mobile team ticket stats add up to the total', function () {
    foreach ([TicketStatus::ASSIGNED, TicketStatus::PENDING_ENDORSEMENT, TicketStatus::RESOLVED, TicketStatus::CLOSED, TicketStatus::CANCELLED] as $status) {
        Ticket::factory()->create(['department_id' => $this->dept->id, 'team_id' => $this->team->id, 'status' => $status->value]);
    }

    Sanctum::actingAs($this->supervisor, ['*']);
    $stats = collect($this->getJson('/api/v1/teams')->assertOk()->json('data'))->firstWhere('id', $this->team->id)['ticket_stats'];

    expect($stats['total'])->toBe(5)
        ->and($stats['pending_endorsement'])->toBe(1)
        ->and($stats['cancelled'])->toBe(1)
        ->and(array_sum($stats) - $stats['total'])->toBe($stats['total']);
});

// --- D2: department list counts ---

test('the department list counts only active supervisors and unfinished tickets', function () {
    $dept = Department::factory()->create(['dept_name' => 'Count Check Division']);
    User::factory()->supervisor($dept->id)->inactive()->create();
    User::factory()->supervisor($dept->id)->create();
    Ticket::factory()->create(['department_id' => $dept->id, 'status' => TicketStatus::CLOSED->value]);
    Ticket::factory()->create(['department_id' => $dept->id, 'status' => TicketStatus::CANCELLED->value]);
    Ticket::factory()->open()->create(['department_id' => $dept->id]);

    $row = $this->actingAs($this->admin)->get(route('admin.departments', ['search' => 'Count Check']))
        ->assertOk()
        ->assertSee('Active Tickets')
        ->viewData('departments')->firstWhere('id', $dept->id);

    expect($row->active_supervisors_count)->toBe(1)
        ->and($row->active_ticket_count)->toBe(1);
});

// --- D3 and D4: endorsed parent ---

test('an endorsed parent with an active child hides force close and explains the missing team', function () {
    $parent = Ticket::factory()->create(['department_id' => $this->dept->id, 'status' => TicketStatus::ENDORSED->value, 'team_id' => null, 'is_endorsed' => true]);
    $child = Ticket::factory()->open()->create(['department_id' => Department::where('id', '!=', $this->dept->id)->first()->id, 'parent_ticket_id' => $parent->id]);

    expect($this->cwd->can('forceClose', $parent))->toBeFalse();

    $this->actingAs($this->cwd)->get(route('cwd.tickets.show', $parent))
        ->assertOk()
        ->assertDontSee(route('cwd.tickets.forceCloseConfirm', $parent))
        ->assertSee('Work transferred through endorsement')
        ->assertDontSee('Awaiting supervisor dispatch');

    $child->update(['status' => TicketStatus::CANCELLED->value]);
    expect($this->cwd->can('forceClose', $parent->fresh()))->toBeTrue();
});

test('the assigned personnel card lists only active supervisors', function () {
    $inactive = User::factory()->supervisor($this->dept->id)->inactive()->create(['first_name' => 'Dormant', 'last_name' => 'Supervisor']);
    $ticket = Ticket::factory()->open()->create(['department_id' => $this->dept->id]);

    $this->actingAs($this->cwd)->get(route('cwd.tickets.show', $ticket))
        ->assertOk()
        ->assertSee($this->supervisor->full_name)
        ->assertDontSee($inactive->full_name);
});

// --- D7: endorsement queue action label ---

test('decided endorsements show View and pending ones show Review', function () {
    $pending = Ticket::factory()->create(['department_id' => $this->dept->id, 'status' => TicketStatus::PENDING_ENDORSEMENT->value]);
    TicketEndorsement::factory()->create(['ticket_id' => $pending->id, 'created_by_id' => $this->supervisor->id, 'status' => EndorsementStatus::PENDING]);
    $rejected = Ticket::factory()->create(['department_id' => $this->dept->id, 'status' => TicketStatus::OPEN->value]);
    TicketEndorsement::factory()->rejected()->create(['ticket_id' => $rejected->id, 'created_by_id' => $this->supervisor->id]);

    $this->actingAs($this->cwd)->get(route('cwd.endorsements'))
        ->assertOk()
        ->assertSee('Review endorsement request for ticket '.$pending->ticket_number)
        ->assertSee('View endorsement request for ticket '.$rejected->ticket_number);
});

// --- Export CSV removed, D8 typo ---

test('the audit log page no longer offers an unavailable CSV export and the dashboard typo is fixed', function () {
    $this->actingAs($this->admin)->get(route('admin.monitoring.index'))
        ->assertOk()
        ->assertDontSee('Export CSV');

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Teams & Department updates')
        ->assertDontSee('Deparment');
});
