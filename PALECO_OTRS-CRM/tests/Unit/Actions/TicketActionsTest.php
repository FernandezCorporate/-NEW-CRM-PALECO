<?php

use App\Actions\Tickets\AddTicketRemark;
use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\CancelTicket;
use App\Actions\Tickets\CreateCwdTicket;
use App\Actions\Tickets\CreateManualChildTicket;
use App\Actions\Tickets\ForceCloseTicket;
use App\Actions\Tickets\StartTicket;
use App\Actions\Tickets\UpdateTicketIntake;
use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Models\Consumer;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('CreateCwdTicket registers ticket with generated sequence and open status', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $department = Department::first();
    $category = TicketCategory::first();

    $this->actingAs($cwdOfficer);

    $action = app(CreateCwdTicket::class);
    $ticket = $action->execute([
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'complaint_description' => 'Power transformer sparking near elementary school.',
        'category_id' => $category->id,
        'department_id' => $department->id,
        'purok' => 'Purok 1',
        'barangay' => 'San Pedro',
        'street' => 'Rizal Avenue',
        'consumer_contact' => '09171234567',
    ]);

    expect($ticket)->toBeInstanceOf(Ticket::class);
    expect($ticket->status)->toBe(TicketStatus::OPEN);
    expect($ticket->ticket_number)->toStartWith('TKT-');
    $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
});

test('CreateCwdTicket links consumer record when account code is provided', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $department = Department::first();
    $category = TicketCategory::first();
    $consumer = Consumer::factory()->create(['acct_code' => '9876543210']);

    $this->actingAs($cwdOfficer);

    $action = app(CreateCwdTicket::class);
    $ticket = $action->execute([
        'link_consumer' => true,
        'account_code' => '9876543210',
        'complaint_source' => ComplaintSources::WALK_IN->value,
        'complaint_description' => 'Billing inquiry and erratic voltage fluctuations.',
        'category_id' => $category->id,
        'department_id' => $department->id,
        'purok' => 'Purok 2',
        'street' => 'San Jose Main St',
        'barangay' => 'San Jose',
        'consumer_contact' => '09179876543',
    ]);

    expect($ticket->consumer_id)->toBe($consumer->id);
});

test('CreateManualChildTicket spawns child ticket linked to parent', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $parent = Ticket::factory()->create([
        'status' => TicketStatus::OPEN->value,
        'parent_ticket_id' => null,
    ]);

    $this->actingAs($cwdOfficer);

    $action = app(CreateManualChildTicket::class);
    $child = $action->execute($parent, [
        'complaint_description' => 'Secondary pole support required.',
        'category_id' => $parent->category_id,
        'department_id' => $parent->department_id,
        'purok' => 'Purok 1',
        'barangay' => 'San Pedro',
    ]);

    expect($child->parent_ticket_id)->toBe($parent->id);
    expect($child->ticket_number)->toBe("{$parent->ticket_number}-1");
});

test('CreateManualChildTicket prevents nested child ticket creation', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $root = Ticket::factory()->create(['parent_ticket_id' => null]);
    $child = Ticket::factory()->create(['parent_ticket_id' => $root->id]);

    $this->actingAs($cwdOfficer);

    $action = app(CreateManualChildTicket::class);

    expect(fn () => $action->execute($child, [
        'complaint_description' => 'Grandchild ticket attempt',
        'department_id' => $root->department_id,
    ]))->toThrow(ValidationException::class);
});

test('UpdateTicketIntake updates clerical details of open ticket', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::OPEN->value]);
    $newCategory = TicketCategory::factory()->create();

    $this->actingAs($cwdOfficer);

    $action = app(UpdateTicketIntake::class);
    $result = $action->execute($ticket, [
        'complaint_source' => ComplaintSources::EMAIL->value,
        'complaint_description' => 'Corrected complaint details after callback.',
        'category_id' => $newCategory->id,
        'department_id' => $ticket->department_id,
        'purok' => 'Purok 5',
        'barangay' => 'Manggahan',
        'consumer_contact' => '09170001111',
    ]);

    expect($result['success'])->toBeTrue();
    expect($ticket->fresh()->complaint_description)->toBe('Corrected complaint details after callback.');
});

test('AssignTicket assigns ticket to team and logs assignment history', function () {
    $supervisor = User::factory()->supervisor()->create();
    $department = Department::first();
    $ticket = Ticket::factory()->create([
        'department_id' => $department->id,
        'status' => TicketStatus::OPEN->value,
    ]);
    $team = Team::factory()->create(['department_id' => $department->id]);

    $action = app(AssignTicket::class);
    $assignedTicket = $action->execute($ticket, $team->id, $supervisor);

    expect($assignedTicket->status)->toBe(TicketStatus::ASSIGNED);
    expect($assignedTicket->team_id)->toBe($team->id);

    $this->assertDatabaseHas('ticket_assignments', [
        'ticket_id' => $ticket->id,
        'team_id' => $team->id,
        'assigned_by_id' => $supervisor->id,
    ]);
});

test('AssignTicket rejects assignment to a team from another department', function () {
    $supervisor = User::factory()->supervisor()->create();
    $dept1 = Department::first();
    $dept2 = Department::factory()->create();

    $ticket = Ticket::factory()->create([
        'department_id' => $dept1->id,
        'status' => TicketStatus::OPEN->value,
    ]);
    $teamInOtherDept = Team::factory()->create(['department_id' => $dept2->id]);

    $action = app(AssignTicket::class);

    expect(fn () => $action->execute($ticket, $teamInOtherDept->id, $supervisor))
        ->toThrow(ValidationException::class);
});

test('StartTicket transitions assigned ticket to in-progress and is idempotent', function () {
    $worker = User::factory()->fieldPersonnel()->create();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::ASSIGNED->value]);

    $action = app(StartTicket::class);

    // Initial start
    $startedTicket = $action->execute($ticket, $worker);
    expect($startedTicket->status)->toBe(TicketStatus::IN_PROGRESS);

    // Idempotent retry returns ticket safely without throwing exception
    $repeatedTicket = $action->execute($ticket->fresh(), $worker);
    expect($repeatedTicket->status)->toBe(TicketStatus::IN_PROGRESS);
});

test('CancelTicket allows CWD officer to cancel open ticket', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::OPEN->value]);

    $action = app(CancelTicket::class);
    $cancelledTicket = $action->execute($ticket, [
        'cancellation_reason' => 'Customer confirmed problem was internal home wiring issue.',
    ], $cwdOfficer);

    expect($cancelledTicket->status)->toBe(TicketStatus::CANCELLED);
});

test('CancelTicket throws error if actor is not CWD officer or ticket is not open', function () {
    $supervisor = User::factory()->supervisor()->create();
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $openTicket = Ticket::factory()->create(['status' => TicketStatus::OPEN->value]);
    $assignedTicket = Ticket::factory()->create(['status' => TicketStatus::ASSIGNED->value]);

    $action = app(CancelTicket::class);

    // Wrong actor role
    expect(fn () => $action->execute($openTicket, ['cancellation_reason' => 'test'], $supervisor))
        ->toThrow(ValidationException::class);

    // Wrong ticket status
    expect(fn () => $action->execute($assignedTicket, ['cancellation_reason' => 'test'], $cwdOfficer))
        ->toThrow(ValidationException::class);
});

test('ForceCloseTicket closes an assigned or in-progress ticket with audit reason', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::IN_PROGRESS->value]);

    $action = app(ForceCloseTicket::class);
    $closedTicket = $action->execute($ticket, [
        'closure_reason' => 'Duplicate ticket reported via emergency radio dispatch.',
    ], $cwdOfficer);

    expect($closedTicket->status)->toBe(TicketStatus::CLOSED);
    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'status' => TicketStatus::CLOSED->value,
    ]);
});

test('AddTicketRemark attaches remarks correctly', function () {
    $user = User::factory()->cwdOfficer()->create();
    $ticket = Ticket::factory()->create();

    $action = app(AddTicketRemark::class);
    $remark = $action->execute($ticket, $user, [
        'body' => 'Spoke with complainant. Feeder line inspected.',
        'is_internal' => true,
    ]);

    expect($remark->body)->toBe('Spoke with complainant. Feeder line inspected.');
    expect($remark->is_internal)->toBeTrue();
    expect($remark->ticket_id)->toBe($ticket->id);
});
