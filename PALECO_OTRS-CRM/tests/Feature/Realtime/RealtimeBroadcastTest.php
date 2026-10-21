<?php

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Events\AccountUpdated;
use App\Events\ReferenceDataUpdated;
use App\Events\TeamUpdated;
use App\Events\TicketEndorsementDecided;
use App\Events\TicketEndorsementInitiated;
use App\Events\TicketIntakeUpdated;
use App\Events\TicketRemarkAdded;
use App\Events\TicketReportRejected;
use App\Events\TicketWorkStarted;
use App\Livewire\Shared\TicketHistoryTabs;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketCategory;
use App\Models\TicketEndorsement;
use App\Models\TicketRemark;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->cwd = User::where('username', 'alliah')->first();
    $this->admin = User::where('username', 'allenglenn')->first();
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->dept = Department::find($this->supervisor->department_id);
    $this->otherDept = Department::where('id', '!=', $this->dept->id)->first();
    $this->team = Team::where('department_id', $this->dept->id)->first();
    $this->worker = User::factory()->fieldPersonnel()->create();
    $this->team->members()->attach($this->worker->id, ['team_role_id' => TeamRole::first()->id]);
});

/**
 * Channel names an event broadcasts on, without the "private-" prefix.
 */
function channelsOf(object $event): array
{
    $channels = $event->broadcastOn();

    return collect(is_array($channels) ? $channels : [$channels])
        ->map(fn (PrivateChannel $channel) => str_replace('private-', '', $channel->name))
        ->all();
}

function intakeEditPayload(Ticket $ticket, int $departmentId): array
{
    return [
        'original_updated_at' => $ticket->updated_at->toIso8601String(),
        'consumer_contact' => '09123456789', 'complaint_source' => 'phone_call', 'complaint_description' => 'No power in the area',
        'purok' => 'Purok 1', 'street' => 'Rizal Street', 'barangay' => 'Poblacion', 'department_id' => $departmentId,
        'other_category' => false, 'category_id' => TicketCategory::first()->id, 'link_consumer' => false,
    ];
}

// --- Ticket events from CWD, supervisors and crews ---

test('an intake edit that re-routes a ticket reaches both departments', function () {
    Event::fake([TicketIntakeUpdated::class]);
    $ticket = Ticket::factory()->open()->create(['department_id' => $this->dept->id]);
    $this->travel(2)->seconds();

    $this->actingAs($this->cwd)->put(route('cwd.tickets.update', $ticket), intakeEditPayload($ticket, $this->otherDept->id))->assertSessionHas('success');

    Event::assertDispatched(TicketIntakeUpdated::class, function (TicketIntakeUpdated $event) use ($ticket) {
        $payload = $event->broadcastWith();

        return $event->ticket->is($ticket)
            && channelsOf($event) === ['cwd.operations', 'department.'.$this->otherDept->id, 'department.'.$this->dept->id]
            && $payload['department_changed'] === true
            && $payload['previous_department_id'] === $this->dept->id;
    });
});

test('a public remark reaches the crew and department, an internal one only CWD and without its text', function () {
    Event::fake([TicketRemarkAdded::class]);
    $ticket = Ticket::factory()->create(['department_id' => $this->dept->id, 'team_id' => $this->team->id, 'status' => TicketStatus::IN_PROGRESS->value]);

    Sanctum::actingAs($this->worker, ['*']);
    $this->postJson("/api/v1/tickets/{$ticket->id}/remarks", ['body' => 'Crew on site.'])->assertCreated();

    $this->actingAs($this->cwd)->post(route('shared.tickets.remarks.store', $ticket), ['body' => 'Internal billing note.', 'is_internal' => true])->assertSessionHas('success');

    Event::assertDispatched(TicketRemarkAdded::class, fn ($event) => ! $event->remark->is_internal
        && channelsOf($event) === ['cwd.operations', 'team.'.$this->team->id, 'department.'.$this->dept->id]
        && $event->broadcastWith()['body'] === 'Crew on site.');

    Event::assertDispatched(TicketRemarkAdded::class, fn ($event) => $event->remark->is_internal
        && channelsOf($event) === ['cwd.operations']
        && $event->broadcastWith()['body'] === null);
});

test('a rejected report broadcasts TicketReportRejected with the reason, not TicketWorkStarted', function () {
    Event::fake([TicketReportRejected::class, TicketWorkStarted::class]);
    $ticket = Ticket::factory()->create(['department_id' => $this->dept->id, 'team_id' => $this->team->id, 'status' => TicketStatus::RESOLVED->value]);
    $report = TicketAccomplishment::factory()->create(['ticket_id' => $ticket->id, 'accomplished_by_id' => $this->worker->id]);

    Sanctum::actingAs($this->supervisor, ['*']);
    $this->postJson("/api/v1/tickets/{$ticket->id}/accomplishments/{$report->id}/verify", ['status' => 'rejected', 'rejection_reason' => 'Photos are blurry.'])->assertOk();

    Event::assertNotDispatched(TicketWorkStarted::class);
    Event::assertDispatched(TicketReportRejected::class, fn ($event) => channelsOf($event) === ['cwd.operations', 'team.'.$this->team->id, 'department.'.$this->dept->id]
        && $event->broadcastWith()['rejection_reason'] === 'Photos are blurry.'
        && $event->broadcastWith()['status'] === 'in_progress');
});

test('endorsement events reach the crew and department, and an approval reaches the crew that lost the ticket', function () {
    Event::fake([TicketEndorsementInitiated::class, TicketEndorsementDecided::class]);
    $ticket = Ticket::factory()->create(['department_id' => $this->dept->id, 'team_id' => $this->team->id, 'status' => TicketStatus::ASSIGNED->value]);

    Sanctum::actingAs($this->supervisor, ['*']);
    $this->postJson("/api/v1/tickets/{$ticket->id}/endorse", ['reason' => 'Belongs to another division.'])->assertCreated();

    Event::assertDispatched(TicketEndorsementInitiated::class, fn ($event) => channelsOf($event) === ['cwd.operations', 'team.'.$this->team->id, 'department.'.$this->dept->id]);

    $endorsement = TicketEndorsement::where('ticket_id', $ticket->id)->first();
    $this->actingAs($this->cwd)->post(route('cwd.endorsements.decide', $endorsement), ['status' => 'approved', 'department_id' => $this->otherDept->id])->assertSessionHas('success');

    Event::assertDispatched(TicketEndorsementDecided::class, function ($event) {
        $payload = $event->broadcastWith();

        return channelsOf($event) === ['cwd.operations', 'team.'.$this->team->id, 'department.'.$this->dept->id]
            && $payload['team_id'] === null
            && $payload['previous_team_id'] === $this->team->id
            && $payload['decision'] === EndorsementStatus::APPROVED->value;
    });
});

// --- Teams and accounts ---

test('a roster edit tells added and removed members on their own channels', function () {
    Event::fake([TeamUpdated::class]);
    $newcomer = User::factory()->fieldPersonnel()->create();
    $role = TeamRole::first();

    Sanctum::actingAs($this->supervisor, ['*']);
    $this->putJson("/api/v1/teams/{$this->team->id}", [
        'original_updated_at' => $this->team->updated_at->toIso8601String(),
        'team_name' => $this->team->team_name,
        'shift_start' => $this->team->shift_start->format('H:i'),
        'shift_end' => $this->team->shift_end->format('H:i'),
        'members' => [['user_id' => $newcomer->id, 'team_role_id' => $role->id]],
    ])->assertOk();

    Event::assertDispatched(TeamUpdated::class, function (TeamUpdated $event) use ($newcomer) {
        $channels = channelsOf($event);

        return $event->action === 'updated'
            && in_array($newcomer->id, $event->addedMemberIds, true)
            && in_array($this->worker->id, $event->removedMemberIds, true)
            && in_array('user.'.$newcomer->id, $channels, true)
            && in_array('user.'.$this->worker->id, $channels, true)
            && in_array('team.'.$this->team->id, $channels, true)
            && in_array('department.'.$this->dept->id, $channels, true);
    });
});

test('deactivating a crew member signs them out and removes them from their team in real time', function () {
    Event::fake([AccountUpdated::class, TeamUpdated::class]);

    $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $this->worker))->assertSessionHas('success');

    Event::assertDispatched(AccountUpdated::class, fn ($event) => $event->user->is($this->worker)
        && channelsOf($event) === ['user.'.$this->worker->id]
        && $event->broadcastWith()['requires_sign_out'] === true);

    Event::assertDispatched(TeamUpdated::class, fn ($event) => $event->team->is($this->team)
        && $event->removedMemberIds === [$this->worker->id]);
});

test('moving a supervisor and resetting a password notify the account', function () {
    Event::fake([AccountUpdated::class]);
    $other = User::factory()->supervisor($this->dept->id)->create();

    $this->actingAs($this->admin)->put(route('admin.users.update', $other), [
        'original_updated_at' => $other->updated_at->toIso8601String(),
        'username' => $other->username, 'first_name' => $other->first_name, 'last_name' => $other->last_name,
        'contact' => '09123456789', 'department_id' => $this->otherDept->id,
    ])->assertSessionHas('success');

    Event::assertDispatched(AccountUpdated::class, fn ($event) => $event->change === 'department_changed'
        && $event->broadcastWith()['previous_department_id'] === $this->dept->id
        && $event->broadcastWith()['department_id'] === $this->otherDept->id);

    $this->actingAs($this->admin)->patch(route('admin.users.updatePassword', $other), ['password' => 'NewPassword1', 'password_confirmation' => 'NewPassword1']);

    Event::assertDispatched(AccountUpdated::class, fn ($event) => $event->change === 'password_reset' && $event->broadcastWith()['requires_sign_out'] === true);
});

test('admin department and category changes broadcast ReferenceDataUpdated', function () {
    Event::fake([ReferenceDataUpdated::class]);

    $this->actingAs($this->admin)->post(route('admin.ticketCategories.store'), ['category_name' => 'Streetlight Outage'])->assertSessionHas('success');
    Event::assertDispatched(ReferenceDataUpdated::class, fn ($event) => $event->type === 'category' && $event->action === 'created' && channelsOf($event) === ['cwd.operations']);

    $empty = Department::factory()->create();
    $this->actingAs($this->admin)->delete(route('admin.departments.archive', $empty->id))->assertSessionHas('success');
    Event::assertDispatched(ReferenceDataUpdated::class, fn ($event) => $event->type === 'department' && $event->action === 'archived'
        && channelsOf($event) === ['cwd.operations', 'department.'.$empty->id]);
});

// --- Channels ---

test('only the account owner may join its personal channel', function () {
    $callback = Broadcast::driver()->getChannels()['user.{userId}'];

    expect($callback($this->worker, $this->worker->id))->toBeTrue()
        ->and($callback($this->supervisor, $this->worker->id))->toBeFalse();
});

// --- Listeners on the web ---

test('the ticket history panel re-renders when a remark is added', function () {
    $ticket = Ticket::factory()->open()->create(['department_id' => $this->dept->id]);
    $component = Livewire::actingAs($this->cwd)->test(TicketHistoryTabs::class, ['ticket' => $ticket]);

    TicketRemark::create(['ticket_id' => $ticket->id, 'user_id' => $this->worker->id, 'body' => 'Live remark from the field.', 'is_internal' => false]);

    $component->dispatch('echo-private:cwd.operations,.TicketRemarkAdded', ['ticket_id' => $ticket->id])
        ->assertSee('Live remark from the field.');
});

test('CWD pages that are not Livewire carry live refresh regions and notices; admin pages do not', function () {
    $ticket = Ticket::factory()->open()->create(['department_id' => $this->dept->id]);

    $this->actingAs($this->cwd)->get(route('cwd.endorsements'))->assertOk()->assertSee('data-live-region="endorsement-rows"', false);
    $this->actingAs($this->cwd)->get(route('cwd.tickets.show', $ticket))->assertOk()->assertSee('data-live-region="ticket-intake"', false);
    $this->actingAs($this->cwd)->get(route('cwd.tickets.editForm', $ticket))->assertOk()->assertSee('data-live-notice', false);

    foreach (['admin.dashboard', 'admin.users', 'admin.teams', 'admin.departments', 'admin.ticketCategories', 'admin.monitoring.index'] as $route) {
        $this->actingAs($this->admin)->get(route($route))->assertOk()->assertDontSee('data-live-', false);
    }

    // Admin's only live pages are the shared ticket list and details
    $this->actingAs($this->admin)->get(route('admin.tickets.show', $ticket))->assertOk()->assertSee('data-live-region="ticket-intake"', false);
});

test('the remarks thread is not frozen by wire:ignore, so Livewire can re-render new remarks', function () {
    $ticket = Ticket::factory()->open()->create(['department_id' => $this->dept->id]);

    $html = $this->actingAs($this->cwd)->get(route('cwd.tickets.show', $ticket))->assertOk()->getContent();

    // Only the form keeps wire:ignore (to preserve a remark being typed); the thread itself must update
    expect($html)->toMatch('/<div id="remark-history" data-tab-panel="remark-history"/')
        ->and($html)->not->toMatch('/<div id="remark-history"[^>]*wire:ignore/');
});

test('the report details page and the cancel and force-close prompts listen for ticket changes', function () {
    $resolved = Ticket::factory()->create(['department_id' => $this->dept->id, 'team_id' => $this->team->id, 'status' => TicketStatus::RESOLVED->value]);
    $report = TicketAccomplishment::factory()->create(['ticket_id' => $resolved->id, 'accomplished_by_id' => $this->worker->id]);
    $open = Ticket::factory()->open()->create(['department_id' => $this->dept->id]);
    $assigned = Ticket::factory()->create(['department_id' => $this->dept->id, 'team_id' => $this->team->id, 'status' => TicketStatus::ASSIGNED->value]);

    $this->actingAs($this->cwd)->get(route('cwd.tickets.accomplishments.show', [$resolved, $report]))
        ->assertOk()->assertSee('data-live-region="report-summary"', false);
    $this->actingAs($this->cwd)->get(route('cwd.tickets.cancelConfirm', $open))
        ->assertOk()->assertSee('data-live-notice', false);
    $this->actingAs($this->cwd)->get(route('cwd.tickets.forceCloseConfirm', $assigned))
        ->assertOk()->assertSee('data-live-notice', false);
});
