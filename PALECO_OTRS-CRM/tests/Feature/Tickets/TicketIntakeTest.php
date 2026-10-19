<?php

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Models\Consumer;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->cwdOfficer = User::where('username', 'alliah')->first();
    $this->admin = User::where('username', 'allenglenn')->first();
    $this->dept = Department::first();
    $this->category = TicketCategory::first();
});

test('cwd officer can view ticket creation form with categories and departments', function () {
    $response = $this->actingAs($this->cwdOfficer)->get(route('cwd.tickets.createForm'));

    $response->assertOk();
    $response->assertViewIs('cwd.forms.ticketForm');
    $response->assertViewHas(['categories', 'departments']);
});

test('admin cannot access cwd ticket creation form', function () {
    $response = $this->actingAs($this->admin)->get(route('cwd.tickets.createForm'));

    $response->assertNotFound();
});

test('cwd officer can register a new service ticket with established category', function () {
    $payload = [
        'consumer_contact' => '09171234567',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'complaint_description' => 'Complete power blackout in residential block.',
        'purok' => 'Purok Liwanag',
        'street' => 'Malvar Street',
        'barangay' => 'San Pedro',
        'landmark' => 'Near Chapel',
        'department_id' => $this->dept->id,
        'other_category' => false,
        'category_id' => $this->category->id,
        'link_consumer' => false,
    ];

    $response = $this->actingAs($this->cwdOfficer)->post(route('cwd.tickets.store'), $payload);

    $response->assertRedirect(route('cwd.tickets'));
    $response->assertSessionHas('success');

    $ticket = Ticket::where('street', 'Malvar Street')->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::OPEN)
        ->and($ticket->ticket_number)->toStartWith('TKT-')
        ->and($ticket->created_by_id)->toBe($this->cwdOfficer->id)
        ->and($ticket->category_id)->toBe($this->category->id);

    // Verify status log was written
    expect($ticket->statusLog()->count())->toBe(1)
        ->and($ticket->statusLog()->first()->new_status)->toBe(TicketStatus::OPEN);
});

test('cwd officer can register a ticket with custom category', function () {
    $payload = [
        'consumer_contact' => '09181234567',
        'complaint_source' => ComplaintSources::WALK_IN->value,
        'complaint_description' => 'Fallen mango tree branch resting on service drop wire.',
        'purok' => 'Purok 4',
        'street' => 'Valencia Street',
        'barangay' => 'Tiniguiban',
        'department_id' => $this->dept->id,
        'other_category' => true,
        'other_category_name' => 'Vegetation Hazard On Wire',
        'link_consumer' => false,
    ];

    $response = $this->actingAs($this->cwdOfficer)->post(route('cwd.tickets.store'), $payload);

    $response->assertRedirect(route('cwd.tickets'));

    $ticket = Ticket::where('street', 'Valencia Street')->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->other_category)->toBeTrue()
        ->and($ticket->other_category_name)->toBe('Vegetation Hazard On Wire')
        ->and($ticket->category_id)->toBeNull();
});

test('ticket intake validation rejects invalid contact format and missing fields', function () {
    $payload = [
        'consumer_contact' => '12345', // Invalid regex
        'complaint_source' => 'carrier_pigeon', // Invalid enum
        'complaint_description' => '', // Required
        'purok' => '',
        'street' => '',
        'barangay' => '',
        'department_id' => 999999, // Non-existent
    ];

    $response = $this->actingAs($this->cwdOfficer)->post(route('cwd.tickets.store'), $payload);

    $response->assertSessionHasErrors([
        'consumer_contact',
        'complaint_source',
        'complaint_description',
        'purok',
        'street',
        'barangay',
        'department_id',
    ]);
});

test('cwd officer can link consumer account during ticket intake', function () {
    $consumer = Consumer::create([
        'acct_code' => '02-0504-8538',
        'acct_no' => '10009999',
        'name' => 'SANTOS, MARIA C.',
        'address' => 'BGY. SAN JOSE, PUERTO PRINCESA CITY',
        'status' => 'Active',
        'meter_serial' => 'MTR-777777',
    ]);

    $payload = [
        'consumer_contact' => '09191234567',
        'complaint_source' => ComplaintSources::ONLINE->value,
        'complaint_description' => 'Low voltage affecting refrigerator and water pump.',
        'purok' => 'Purok 1',
        'street' => 'National Highway',
        'barangay' => 'San Jose',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'link_consumer' => true,
        'account_code' => '02-0504-8538',
    ];

    $response = $this->actingAs($this->cwdOfficer)->post(route('cwd.tickets.store'), $payload);

    $response->assertRedirect(route('cwd.tickets'));

    $ticket = Ticket::where('street', 'National Highway')->first();
    expect($ticket->consumer_id)->toBe($consumer->id);
});

test('cwd officer can update ticket intake details while ticket remains open', function () {
    $ticket = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
        'street' => 'Old Street Name',
    ]);

    $updatePayload = [
        'consumer_contact' => '09201234567',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'complaint_description' => 'Updated complaint description with more specific details.',
        'purok' => 'Purok Updated',
        'street' => 'Corrected Street Name',
        'barangay' => 'San Pedro',
        'landmark' => 'Near Water Tower',
        'department_id' => $this->dept->id,
        'other_category' => false,
        'category_id' => $this->category->id,
        'link_consumer' => false,
    ];

    $response = $this->actingAs($this->cwdOfficer)
        ->put(route('cwd.tickets.update', ['ticket' => $ticket]), $updatePayload);

    $response->assertRedirect(route('cwd.tickets.show', ['ticket' => $ticket]));
    $response->assertSessionHas('success');

    expect($ticket->fresh()->street)->toBe('Corrected Street Name')
        ->and($ticket->fresh()->purok)->toBe('Purok Updated');
});

test('updating ticket intake details fails if ticket has progressed past open status', function () {
    $ticket = Ticket::factory()->inProgress()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);

    $updatePayload = [
        'consumer_contact' => '09201234567',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'complaint_description' => 'Trying to modify an in-progress ticket.',
        'purok' => 'Purok 1',
        'street' => 'Street Name',
        'barangay' => 'San Pedro',
        'department_id' => $this->dept->id,
        'other_category' => false,
        'category_id' => $this->category->id,
        'link_consumer' => false,
    ];

    // Policy should forbid updating tickets that are not open
    $response = $this->actingAs($this->cwdOfficer)
        ->put(route('cwd.tickets.update', ['ticket' => $ticket]), $updatePayload);

    $response->assertForbidden();
});

test('cwd officer can spawn a child ticket from an open parent ticket', function () {
    $parentTicket = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
        'purok' => 'Purok 1',
        'street' => 'Rizal Avenue',
        'barangay' => 'San Pedro',
    ]);

    $secondDept = Department::skip(1)->first() ?? Department::factory()->create();

    $childPayload = [
        'department_id' => $secondDept->id,
        'complaint_description' => 'Child ticket requiring secondary assistance from another department.',
        'category_id' => $this->category->id,
        'other_category' => false,
        'purok' => 'Purok 1',
        'street' => 'Rizal Avenue',
        'barangay' => 'San Pedro',
    ];

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.children.store', ['ticket' => $parentTicket]), $childPayload);

    $childTicket = Ticket::where('parent_ticket_id', $parentTicket->id)->first();
    expect($childTicket)->not->toBeNull();

    $response->assertRedirect(route('cwd.tickets.show', ['ticket' => $childTicket]));
    $response->assertSessionHas('success');

    expect($childTicket->parent_ticket_id)->toBe($parentTicket->id)
        ->and($childTicket->department_id)->toBe($secondDept->id)
        ->and($childTicket->ticket_number)->toStartWith($parentTicket->ticket_number);

    // Parent should have internal remark noting child creation
    expect($parentTicket->remarks()->count())->toBe(1)
        ->and($parentTicket->remarks()->first()->is_internal)->toBeTrue();
});

test('cannot spawn a child ticket from another child ticket', function () {
    $parent = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
        'purok' => 'Purok 1',
        'street' => 'Street',
        'barangay' => 'San Pedro',
    ]);

    $child = Ticket::factory()->open()->create([
        'parent_ticket_id' => $parent->id,
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
        'purok' => 'Purok 1',
        'street' => 'Street',
        'barangay' => 'San Pedro',
    ]);

    $childPayload = [
        'department_id' => $this->dept->id,
        'complaint_description' => 'Illegal grandchild attempt.',
        'category_id' => $this->category->id,
        'other_category' => false,
        'purok' => 'Purok 1',
        'street' => 'Street',
        'barangay' => 'San Pedro',
    ];

    // Policy createChild checks ! $ticket->isChild()
    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.children.store', ['ticket' => $child]), $childPayload);

    $response->assertForbidden();
});

test('cannot spawn a child ticket from a cancelled or closed parent ticket', function (string $stateMethod) {
    $parent = Ticket::factory()->$stateMethod()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
        'purok' => 'Purok 1',
        'street' => 'Street',
        'barangay' => 'San Pedro',
    ]);

    $childPayload = [
        'department_id' => $this->dept->id,
        'complaint_description' => 'Attempting to spawn from terminal status.',
        'category_id' => $this->category->id,
        'other_category' => false,
        'purok' => 'Purok 1',
        'street' => 'Street',
        'barangay' => 'San Pedro',
    ];

    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('cwd.tickets.children.store', ['ticket' => $parent]), $childPayload);

    $response->assertForbidden();
})->with(['cancelled', 'closed']);
