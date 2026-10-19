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
        ->and($endorsement->pre_endorsement_status)->toBe(TicketStatus::IN_PROGRESS)
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

