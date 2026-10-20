<?php

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketEndorsement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->cwdOfficer = User::where('username', 'alliah')->first();

    $this->dept1 = Department::first();
    $this->dept2 = Department::skip(1)->first();

    $this->supervisor->update(['department_id' => $this->dept1->id]);
    $this->team = Team::where('department_id', $this->dept1->id)->first() ?? Team::first();
    $this->category = TicketCategory::first();

    $this->ticket = Ticket::factory()->inProgress($this->team)->create([
        'department_id' => $this->dept1->id,
        'category_id' => $this->category->id,
    ]);
});

test('supervisor can view endorsement department options excluding current department', function () {
    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->getJson("/api/v1/tickets/{$this->ticket->id}/endorse-options");

    $response->assertOk();
    $response->assertJsonPath('success', true);

    $deptIds = collect($response->json('data'))->pluck('id')->all();
    expect($deptIds)->not->toContain($this->dept1->id)
        ->and($deptIds)->toContain($this->dept2->id);
});

test('supervisor can initiate endorsement request freezing ticket in pending_endorsement', function () {
    $this->ticket->update(['status' => TicketStatus::ASSIGNED]);
    Sanctum::actingAs($this->supervisor, ['*']);

    $payload = [
        'suggested_department_id' => $this->dept2->id,
        'reason' => 'Root issue requires heavy line construction unit with crane bucket.',
    ];

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/endorse", $payload);

    $response->assertCreated();
    $response->assertJsonPath('success', true);

    $this->ticket->refresh();
    expect($this->ticket->status)->toBe(TicketStatus::PENDING_ENDORSEMENT);

    $endorsement = TicketEndorsement::where('ticket_id', $this->ticket->id)->first();
    expect($endorsement)->not->toBeNull()
        ->and($endorsement->status)->toBe(EndorsementStatus::PENDING)
        ->and($endorsement->pre_endorsement_status)->toBe(TicketStatus::ASSIGNED)
        ->and($endorsement->suggested_department_id)->toBe($this->dept2->id)
        ->and($endorsement->created_by_id)->toBe($this->supervisor->id);
});

test('initiating duplicate endorsement while already pending is rejected', function () {
    TicketEndorsement::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
        'status' => EndorsementStatus::PENDING,
    ]);
    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT]);

    Sanctum::actingAs($this->supervisor, ['*']);

    $payload = [
        'suggested_department_id' => $this->dept2->id,
        'reason' => 'Duplicate attempt.',
    ];

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/endorse", $payload);

    // Policy or action rejects
    expect($response->status())->toBeIn([403, 422]);
});

test('cwd officer can view endorsement queue in web portal', function () {
    TicketEndorsement::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
        'status' => EndorsementStatus::PENDING,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->get(route('cwd.endorsements'));

    $response->assertOk();
    $response->assertViewIs('cwd.pages.endorsementDashboard');
    $response->assertViewHas('endorsements');
});

test('cwd officer can view single endorsement detail', function () {
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
        'status' => EndorsementStatus::PENDING,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->get(route('cwd.endorsements.show', ['endorsement' => $endorsement]));

    $response->assertOk();
    $response->assertViewIs('cwd.pages.endorsementDetails');
    $response->assertViewHas('endorsement');
});

test('cwd officer can approve endorsement spawning child ticket and updating parent to endorsed', function () {
    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT]);

    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
        'status' => EndorsementStatus::PENDING,
        'pre_endorsement_status' => TicketStatus::IN_PROGRESS,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => 'approved',
            'department_id' => $this->dept2->id,
        ]);

    $response->assertRedirect(route('cwd.endorsements'));
    $response->assertSessionHas('success');

    $endorsement->refresh();
    expect($endorsement->status)->toBe(EndorsementStatus::APPROVED)
        ->and($endorsement->reviewed_by_id)->toBe($this->cwdOfficer->id)
        ->and($endorsement->reviewed_at)->not->toBeNull();

    $this->ticket->refresh();
    expect($this->ticket->status)->toBe(TicketStatus::ENDORSED);

    // Verify child ticket was spawned
    $child = Ticket::where('parent_ticket_id', $this->ticket->id)->first();
    expect($child)->not->toBeNull()
        ->and($child->department_id)->toBe($this->dept2->id)
        ->and($child->status)->toBe(TicketStatus::OPEN);
});

test('cwd officer can reject endorsement reverting parent ticket to pre_endorsement_status', function () {
    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT]);

    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
        'status' => EndorsementStatus::PENDING,
        'pre_endorsement_status' => TicketStatus::IN_PROGRESS,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => 'rejected',
            'rejection_reason' => 'Existing crew has necessary gear; handle within technical division.',
        ]);

    $response->assertRedirect(route('cwd.endorsements'));
    $response->assertSessionHas('success');

    $endorsement->refresh();
    expect($endorsement->status)->toBe(EndorsementStatus::REJECTED)
        ->and($endorsement->reviewed_by_id)->toBe($this->cwdOfficer->id)
        ->and($endorsement->rejection_reason)->toContain('Existing crew');

    $this->ticket->refresh();
    expect($this->ticket->status)->toBe(TicketStatus::IN_PROGRESS);
});

test('approving endorsement requires target department_id different from originating department', function () {
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
        'status' => EndorsementStatus::PENDING,
    ]);

    // Same department
    $responseSame = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => 'approved',
            'department_id' => $this->dept1->id,
        ]);

    $responseSame->assertSessionHasErrors(['department_id']);

    // Missing department
    $responseMissing = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => 'approved',
        ]);

    $responseMissing->assertSessionHasErrors(['department_id']);
});

test('rejecting endorsement requires rejection_reason note', function () {
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
        'status' => EndorsementStatus::PENDING,
    ]);

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => 'rejected',
            'rejection_reason' => '',
        ]);

    $response->assertSessionHasErrors(['rejection_reason']);
});

test('endorsement queue can be searched by the creator name despite encryption', function () {
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
        'status' => EndorsementStatus::PENDING,
    ]);

    // Seeded supervisor: first name "mycka", last name "supervisor"
    foreach (['Mycka', 'supervisor', 'mycka supervisor'] as $term) {
        $found = $this->actingAs($this->cwdOfficer)
            ->get(route('cwd.endorsements', ['search' => $term]))
            ->assertOk()
            ->viewData('endorsements');

        expect($found->pluck('id')->all())->toContain($endorsement->id);
    }

    $none = $this->actingAs($this->cwdOfficer)
        ->get(route('cwd.endorsements', ['search' => 'nonexistent-person']))
        ->viewData('endorsements');

    expect($none->total())->toBe(0);
});

test('supervisor cannot endorse or load endorsement options for a child ticket', function () {
    $child = Ticket::factory()->inProgress($this->team)->create([
        'parent_ticket_id' => $this->ticket->id,
        'ticket_number' => $this->ticket->ticket_number.'-1',
        'department_id' => $this->dept1->id,
        'category_id' => $this->category->id,
    ]);

    Sanctum::actingAs($this->supervisor, ['*']);

    $this->getJson("/api/v1/tickets/{$child->id}/endorse-options")->assertForbidden();

    $response = $this->postJson("/api/v1/tickets/{$child->id}/endorse", [
        'suggested_department_id' => $this->dept2->id,
        'reason' => 'Attempting to re-route a child ticket.',
    ]);

    $response->assertForbidden();
    $response->assertJsonPath('message', 'Child tickets cannot be endorsed. Ask the CWD officer to create a new child ticket from the origin ticket instead.');

    expect($child->fresh()->status)->toBe(TicketStatus::IN_PROGRESS)
        ->and(TicketEndorsement::where('ticket_id', $child->id)->exists())->toBeFalse();
});

test('pending endorsement on a child ticket cannot be approved but can be rejected', function () {
    $child = Ticket::factory()->create([
        'parent_ticket_id' => $this->ticket->id,
        'ticket_number' => $this->ticket->ticket_number.'-1',
        'department_id' => $this->dept1->id,
        'category_id' => $this->category->id,
        'status' => TicketStatus::PENDING_ENDORSEMENT,
    ]);

    // Legacy state: endorsement created on a child ticket before the rule existed
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $child->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
        'status' => EndorsementStatus::PENDING,
        'pre_endorsement_status' => TicketStatus::OPEN,
    ]);

    $approve = $this->actingAs($this->cwdOfficer)
        ->from(route('cwd.endorsements.show', ['endorsement' => $endorsement]))
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => 'approved',
            'department_id' => $this->dept2->id,
        ]);

    $approve->assertRedirect(route('cwd.endorsements.show', ['endorsement' => $endorsement]));
    $approve->assertSessionHas('error');

    expect($endorsement->fresh()->status)->toBe(EndorsementStatus::PENDING)
        ->and($child->fresh()->status)->toBe(TicketStatus::PENDING_ENDORSEMENT)
        ->and(Ticket::where('parent_ticket_id', $child->id)->exists())->toBeFalse();

    $reject = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => 'rejected',
            'rejection_reason' => 'Child tickets cannot be re-routed; CWD will create a new child ticket.',
        ]);

    $reject->assertRedirect(route('cwd.endorsements'));
    expect($endorsement->fresh()->status)->toBe(EndorsementStatus::REJECTED)
        ->and($child->fresh()->status)->toBe(TicketStatus::OPEN);
});


test('an in-progress ticket cannot be endorsed because the crew already accepted the work', function () {
    Sanctum::actingAs($this->supervisor, ['*']);

    $this->postJson("/api/v1/tickets/{$this->ticket->id}/endorse", [
        'suggested_department_id' => $this->dept2->id,
        'reason' => 'Needs a crane bucket.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['status' => 'Only Open or Assigned tickets can be endorsed. Once work has started, the ticket stays with this department.']);

    expect($this->ticket->fresh()->status)->toBe(TicketStatus::IN_PROGRESS)
        ->and(TicketEndorsement::where('ticket_id', $this->ticket->id)->exists())->toBeFalse();
});

test('an open ticket can be endorsed', function () {
    $open = Ticket::factory()->open()->create([
        'department_id' => $this->dept1->id,
        'category_id' => $this->category->id,
    ]);
    Sanctum::actingAs($this->supervisor, ['*']);

    $this->postJson("/api/v1/tickets/{$open->id}/endorse", ['reason' => 'Belongs to metering.'])->assertCreated();

    expect($open->fresh()->status)->toBe(TicketStatus::PENDING_ENDORSEMENT);
});

test('an endorsement decision of pending is refused and changes nothing', function () {
    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT]);
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'status' => EndorsementStatus::PENDING,
        'pre_endorsement_status' => TicketStatus::ASSIGNED,
    ]);

    $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => 'pending',
            'rejection_reason' => 'Crafted request.',
        ])
        ->assertSessionHasErrors('status');

    expect($endorsement->fresh()->status)->toBe(EndorsementStatus::PENDING)
        ->and($this->ticket->fresh()->status)->toBe(TicketStatus::PENDING_ENDORSEMENT);
});

test('an endorsement records the from and to departments and marks the parent as endorsed', function () {
    $this->ticket->update(['status' => TicketStatus::ASSIGNED]);
    Sanctum::actingAs($this->supervisor, ['*']);

    // The supervisor suggests one department; CWD transfers to another
    $dept3 = Department::factory()->create();
    $this->postJson("/api/v1/tickets/{$this->ticket->id}/endorse", [
        'suggested_department_id' => $this->dept2->id,
        'reason' => 'Needs another division.',
    ])->assertCreated()->assertJsonPath('data.old_dept', $this->dept1->dept_name);

    $endorsement = TicketEndorsement::where('ticket_id', $this->ticket->id)->first();
    expect($endorsement->old_department_id)->toBe($this->dept1->id)
        ->and($endorsement->new_department_id)->toBeNull();

    // A later move of the requesting supervisor doesn't change the record
    $this->supervisor->update(['department_id' => $dept3->id]);

    $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => 'approved',
            'department_id' => $dept3->id,
        ])->assertSessionHas('success');

    $endorsement->refresh();
    expect($endorsement->old_department_id)->toBe($this->dept1->id)
        ->and($endorsement->new_department_id)->toBe($dept3->id)
        ->and($endorsement->suggested_department_id)->toBe($this->dept2->id)
        ->and($this->ticket->fresh()->is_endorsed)->toBeTrue();

    // is_endorsed survives the parent's force-close
    Ticket::where('parent_ticket_id', $this->ticket->id)->update(['status' => TicketStatus::CANCELLED]);
    $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.forceClose', ['ticket' => $this->ticket]), [
            'closure_reason' => 'Transferred work completed by the other department.',
        ])->assertSessionHas('success');

    $this->ticket->refresh();
    expect($this->ticket->status)->toBe(TicketStatus::CLOSED)
        ->and($this->ticket->is_endorsed)->toBeTrue();

    // The CWD queue and detail pages show From and To
    $this->actingAs($this->cwdOfficer)->get(route('cwd.endorsements'))
        ->assertOk()->assertSee('From &rarr; To', false)->assertSee($dept3->dept_name);
    $this->actingAs($this->cwdOfficer)->get(route('cwd.endorsements.show', $endorsement))
        ->assertOk()->assertSee('Transferred To')->assertDontSee('Suggested Department');
});

test('a rejected endorsement keeps no new department and the ticket is not endorsed', function () {
    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT]);
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
        'status' => EndorsementStatus::PENDING,
        'pre_endorsement_status' => TicketStatus::ASSIGNED,
    ]);

    // The pending details page shows the supervisor's suggestion
    $this->actingAs($this->cwdOfficer)->get(route('cwd.endorsements.show', $endorsement))
        ->assertOk()->assertSee('Suggested Department')->assertSee($this->dept2->dept_name);

    $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => 'rejected',
            'rejection_reason' => 'Handle within the division.',
        ])->assertSessionHas('success');

    expect($endorsement->fresh()->new_department_id)->toBeNull()
        ->and($endorsement->fresh()->old_department_id)->toBe($this->dept1->id)
        ->and($this->ticket->fresh()->is_endorsed)->toBeFalse();
});

test('mobile ticket and history payloads expose is_endorsed and the from and to departments', function () {
    $this->ticket->update(['status' => TicketStatus::ENDORSED, 'team_id' => null, 'is_endorsed' => true]);
    TicketEndorsement::factory()->approved()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
    ]);
    Sanctum::actingAs($this->supervisor, ['*']);

    $this->getJson("/api/v1/tickets/{$this->ticket->id}")
        ->assertOk()->assertJsonPath('data.is_endorsed', true);

    $this->getJson("/api/v1/tickets/{$this->ticket->id}/history")
        ->assertOk()
        ->assertJsonPath('data.endorsements.0.old_dept', $this->dept1->dept_name)
        ->assertJsonPath('data.endorsements.0.new_dept', $this->dept2->dept_name);
});

test('a child created by an endorsement shows where it came from, and a manual child does not', function () {
    $this->ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT]);
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => $this->dept2->id,
        'reason' => 'Needs a crane bucket from the other division.',
        'status' => EndorsementStatus::PENDING,
        'pre_endorsement_status' => TicketStatus::ASSIGNED,
    ]);

    $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => 'approved',
            'department_id' => $this->dept2->id,
        ])->assertSessionHas('success');

    $child = Ticket::where('parent_ticket_id', $this->ticket->id)->firstOrFail();
    expect($endorsement->fresh()->child_ticket_id)->toBe($child->id)
        ->and($child->createdVia())->toBe('endorsement')
        ->and($this->ticket->fresh()->createdVia())->toBeNull();

    // The receiving supervisor sees the origin on mobile
    $receiver = User::factory()->supervisor($this->dept2->id)->create();
    Sanctum::actingAs($receiver, ['*']);

    $this->getJson("/api/v1/tickets/{$child->id}")
        ->assertOk()
        ->assertJsonPath('data.created_via', 'endorsement')
        ->assertJsonPath('data.endorsement_origin.reason', 'Needs a crane bucket from the other division.')
        ->assertJsonPath('data.endorsement_origin.from_dept', $this->dept1->dept_name)
        ->assertJsonPath('data.endorsement_origin.approved_by', $this->cwdOfficer->full_name);

    $inbox = collect($this->getJson('/api/v1/tickets')->assertOk()->json('data'));
    expect($inbox->firstWhere('id', $child->id)['created_via'])->toBe('endorsement');

    // CWD sees it on the web details page
    $this->actingAs($this->cwdOfficer)->get(route('cwd.tickets.show', $child))
        ->assertOk()
        ->assertSee('Endorsement from '.$this->dept1->dept_name)
        ->assertSee('Needs a crane bucket from the other division.');

    // A manual child of the same origin is marked manual, with no endorsement origin
    $manual = Ticket::factory()->open()->create([
        'parent_ticket_id' => $this->ticket->id,
        'department_id' => $this->dept2->id,
        'category_id' => $this->category->id,
    ]);

    $this->getJson("/api/v1/tickets/{$manual->id}")
        ->assertOk()
        ->assertJsonPath('data.created_via', 'manual')
        ->assertJsonPath('data.endorsement_origin', null);

    $this->actingAs($this->cwdOfficer)->get(route('cwd.tickets.show', $manual))
        ->assertOk()->assertSee('Created manually by a CWD officer');
});
