<?php

use App\Enums\ComplaintSources;
use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketEndorsement;
use App\Models\TicketRemark;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
    $this->admin = User::where('username', 'allenglenn')->first();
    $this->cwdOfficer = User::where('username', 'alliah')->first();
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->dept = Department::first();
    $this->category = TicketCategory::first();
});

// Sends the same request the browser's live validation sends
function precognitionHeaders(array $only = []): array
{
    $headers = ['Precognition' => 'true', 'Accept' => 'application/json'];
    if ($only) {
        $headers['Precognition-Validate-Only'] = implode(',', $only);
    }

    return $headers;
}

test('every web form route with a form request accepts precognitive requests', function (string $routeName) {
    $route = Route::getRoutes()->getByName($routeName);

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain(HandlePrecognitiveRequests::class);
})->with([
    'admin.users.store',
    'admin.users.update',
    'admin.users.updatePassword',
    'admin.departments.store',
    'admin.departments.update',
    'admin.teams.store',
    'admin.teams.update',
    'admin.ticketCategories.store',
    'admin.ticketCategories.update',
    'cwd.tickets.store',
    'cwd.tickets.update',
    'cwd.tickets.children.store',
    'cwd.tickets.cancel',
    'cwd.tickets.forceClose',
    'cwd.endorsements.decide',
    'shared.tickets.remarks.store',
]);

test('an empty ticket returns every required field message from the form request without creating a ticket', function () {
    $response = $this->actingAs($this->cwdOfficer)
        ->withHeaders(precognitionHeaders())
        ->post(route('cwd.tickets.store'), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'consumer_contact', 'complaint_source', 'complaint_description',
            'purok', 'street', 'barangay', 'department_id', 'category_id',
        ])
        ->assertJsonPath('errors.purok.0', 'The Purok field is required for location routing.')
        ->assertJsonPath('errors.department_id.0', 'Please select a department to handle this ticket.');

    expect(Ticket::count())->toBe(0);
});

test('validate-only checks just the fields the user has touched', function () {
    $response = $this->actingAs($this->cwdOfficer)
        ->withHeaders(precognitionHeaders(['purok', 'consumer_contact']))
        ->post(route('cwd.tickets.store'), ['consumer_contact' => '12345']);

    $response->assertStatus(422);
    expect(array_keys($response->json('errors')))->toEqualCanonicalizing(['purok', 'consumer_contact'])
        ->and($response->json('errors.consumer_contact.0'))
        ->toBe('The contact number must be a valid 11-digit mobile number (e.g., 09123456789).');
});

test('a valid ticket passes the check with 204 and is not saved until the real submit', function () {
    $payload = [
        'consumer_contact' => '09171234567',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'complaint_description' => 'Complete power blackout in residential block.',
        'purok' => 'Purok Liwanag',
        'street' => 'Malvar Street',
        'barangay' => 'San Pedro',
        'department_id' => $this->dept->id,
        'other_category' => '0',
        'category_id' => $this->category->id,
        'link_consumer' => '0',
    ];

    $this->actingAs($this->cwdOfficer)
        ->withHeaders(precognitionHeaders())
        ->post(route('cwd.tickets.store'), $payload)
        ->assertNoContent()
        ->assertHeader('Precognition-Success', 'true');

    expect(Ticket::count())->toBe(0);
});

test('the form request still prepares its data first, so a custom category needs its name', function () {
    $response = $this->actingAs($this->cwdOfficer)
        ->withHeaders(precognitionHeaders(['category_id', 'other_category_name']))
        ->post(route('cwd.tickets.store'), ['other_category' => '1', 'category_id' => $this->category->id]);

    $response->assertStatus(422);
    expect(array_keys($response->json('errors')))->toBe(['other_category_name']);
});

test('the role gate still applies to precognitive requests', function () {
    $this->actingAs($this->admin)
        ->withHeaders(precognitionHeaders())
        ->post(route('cwd.tickets.store'), [])
        ->assertNotFound();
});

test('admin user form reports password and contact problems without creating the account', function () {
    $response = $this->actingAs($this->admin)
        ->withHeaders(precognitionHeaders(['password', 'contact']))
        ->post(route('admin.users.store'), [
            'contact' => '0917',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['password', 'contact']);
    expect(User::where('contact', '0917')->exists())->toBeFalse();
});

test('team member rows are reported per row with their array key', function () {
    $response = $this->actingAs($this->admin)
        ->withHeaders(precognitionHeaders())
        ->post(route('admin.teams.store'), [
            'team_name' => 'Live Validation Crew',
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'department_id' => $this->dept->id,
            'members' => [['user_id' => '', 'team_role_id' => TeamRole::first()->id]],
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['members.0.user_id']);
    expect(Team::where('team_name', 'Live Validation Crew')->exists())->toBeFalse();
});

test('cancelling without a reason is reported and the ticket stays open', function () {
    $ticket = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'created_by_id' => $this->cwdOfficer->id,
    ]);

    $this->actingAs($this->cwdOfficer)
        ->withHeaders(precognitionHeaders())
        ->post(route('cwd.tickets.cancel', ['ticket' => $ticket]), ['cancellation_reason' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cancellation_reason']);

    expect($ticket->fresh()->status)->toBe(TicketStatus::OPEN);
});

test('rejecting an endorsement without a reason is reported and the endorsement stays pending', function () {
    $ticket = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $ticket->id,
        'created_by_id' => $this->supervisor->id,
        'suggested_department_id' => Department::skip(1)->first()->id,
        'status' => EndorsementStatus::PENDING,
    ]);

    $this->actingAs($this->cwdOfficer)
        ->withHeaders(precognitionHeaders())
        ->post(route('cwd.endorsements.decide', ['endorsement' => $endorsement]), [
            'status' => EndorsementStatus::REJECTED->value,
            'rejection_reason' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['rejection_reason']);

    expect($endorsement->fresh()->status)->toBe(EndorsementStatus::PENDING);
});

test('a remark that passes the check is not posted until the real submit', function () {
    $ticket = Ticket::factory()->open()->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);

    $this->actingAs($this->cwdOfficer)
        ->withHeaders(precognitionHeaders())
        ->post(route('shared.tickets.remarks.store', ['ticket' => $ticket]), ['body' => 'Crew is on the way.'])
        ->assertNoContent();

    expect(TicketRemark::where('ticket_id', $ticket->id)->count())->toBe(0);
});

test('a normal submit is unchanged and still redirects back with session errors', function () {
    $this->actingAs($this->cwdOfficer)
        ->from(route('cwd.tickets.createForm'))
        ->post(route('cwd.tickets.store'), [])
        ->assertRedirect(route('cwd.tickets.createForm'))
        ->assertSessionHasErrors(['purok', 'department_id']);
});

test('form pages mark their forms for live validation', function () {
    $this->actingAs($this->cwdOfficer)
        ->get(route('cwd.tickets.createForm'))
        ->assertOk()
        ->assertSee('data-live-validation', false);

    $this->actingAs($this->admin)
        ->get(route('admin.users.createForm'))
        ->assertOk()
        ->assertSee('data-live-validation', false);
});
