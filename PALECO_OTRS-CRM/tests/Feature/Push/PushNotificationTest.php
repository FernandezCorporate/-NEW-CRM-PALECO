<?php

use App\Actions\Endorsements\InitiateEndorsement;
use App\Actions\Users\UpdateUserPermissions;
use App\Enums\EndorsementStatus;
use App\Enums\TicketAccomplishmentStatus;
use App\Enums\TicketStatus;
use App\Events\TeamUpdated;
use App\Events\TicketAccomplished;
use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketEndorsementDecided;
use App\Events\TicketForceClosed;
use App\Events\TicketRemarkAdded;
use App\Events\TicketReportRejected;
use App\Events\TicketVerifiedAndClosed;
use App\Events\TicketWorkStarted;
use App\Models\Consumer;
use App\Models\Department;
use App\Models\PushDevice;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketEndorsement;
use App\Models\TicketRemark;
use App\Models\User;
use App\Services\Push\FcmClient;
use App\Services\Push\PushGateway;
use App\Services\Push\PushMessage;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakePushGateway;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
    $this->withoutDefer();

    $this->push = new FakePushGateway;
    $this->app->instance(PushGateway::class, $this->push);

    $this->admin = User::where('username', 'allenglenn')->first();
    $this->cwdOfficer = User::where('username', 'alliah')->first();
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->personnel = User::where('username', 'ralph')->first();
    $this->team = Team::first();

    // Supervisor, crew and tickets in one department
    $this->supervisor->update(['department_id' => $this->team->department_id]);
    $this->dept = Department::find($this->team->department_id);
});

// Registers a phone for an account, the way Phase 1's endpoint does
function givePhone(User $user): PushDevice
{
    $login = $user->createToken('Phone of '.$user->username);

    return PushDevice::create([
        'user_id' => $user->id,
        'personal_access_token_id' => $login->accessToken->id,
        'fcm_token' => 'fcm-token-for-'.$user->username.'-0123456789',
        'last_seen_at' => now(),
    ]);
}

function deptTicket($test, array $attributes = []): Ticket
{
    return Ticket::factory()->create(array_merge([
        'department_id' => $test->dept->id,
        'created_by_id' => $test->cwdOfficer->id,
        'barangay' => 'San Pedro',
    ], $attributes));
}

test('a new ticket notifies the department supervisors who can assign it', function () {
    givePhone($this->supervisor);
    givePhone($this->personnel);
    $ticket = deptTicket($this);

    $this->actingAs($this->cwdOfficer);
    event(new TicketCreated($ticket));

    $messages = $this->push->messages();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->userId)->toBe($this->supervisor->id)
        ->and($messages[0]->type)->toBe('ticket_created')
        ->and($messages[0]->body)->toContain($ticket->ticket_number)->toContain('Brgy. San Pedro')
        ->and($messages[0]->channel)->toBe(PushMessage::CHANNEL_UPDATES);
});

test('a supervisor without the assign permission is not alerted about new tickets', function () {
    givePhone($this->supervisor);
    $keep = array_values(array_diff(PermissionCatalog::forRole('supervisor'), ['tickets.assign']));
    app(UpdateUserPermissions::class)->execute($this->supervisor, $keep, $this->admin);

    event(new TicketCreated(deptTicket($this)));

    expect($this->push->messages())->toBeEmpty();
});

test('a submitted report notifies supervisors who can verify it', function () {
    givePhone($this->supervisor);

    event(new TicketAccomplished(deptTicket($this, ['team_id' => $this->team->id, 'status' => TicketStatus::RESOLVED])));

    expect($this->push->ofType('report_submitted'))->toHaveCount(1);
});

test('an assignment through the api sends an urgent dispatch to the crew and nothing to the supervisor who assigned', function () {
    givePhone($this->supervisor);
    givePhone($this->personnel);
    $ticket = deptTicket($this, ['status' => TicketStatus::OPEN]);
    Sanctum::actingAs($this->supervisor, ['*']);

    $this->postJson("/api/v1/tickets/{$ticket->id}/assign", ['team_id' => $this->team->id])->assertOk();

    $dispatch = $this->push->ofType('ticket_assigned');
    expect($dispatch)->toHaveCount(1)
        ->and($dispatch[0]->userId)->toBe($this->personnel->id)
        ->and($dispatch[0]->urgent)->toBeTrue()
        ->and($dispatch[0]->channel)->toBe(PushMessage::CHANNEL_DISPATCH)
        ->and($this->push->messages()->where('userId', $this->supervisor->id))->toBeEmpty();
});

test('the previous crew is told when a ticket is reassigned away from them', function () {
    givePhone($this->personnel);
    $newTeam = Team::factory()->create(['department_id' => $this->dept->id]);
    $ticket = deptTicket($this, ['team_id' => $newTeam->id, 'status' => TicketStatus::ASSIGNED]);

    event(new TicketAssigned($ticket, $this->team->id));

    $away = $this->push->ofType('ticket_reassigned_away');
    expect($away)->toHaveCount(1)
        ->and($away[0]->userId)->toBe($this->personnel->id)
        ->and($away[0]->urgent)->toBeFalse();
});

test('a force-closed ticket sends an urgent stop-work alert to the crew and a normal one to supervisors', function () {
    givePhone($this->personnel);
    givePhone($this->supervisor);
    $ticket = deptTicket($this, ['team_id' => $this->team->id, 'status' => TicketStatus::CLOSED, 'is_force_closed' => true]);

    $this->actingAs($this->cwdOfficer);
    event(new TicketForceClosed($ticket));

    $crew = $this->push->messages()->firstWhere('userId', $this->personnel->id);
    $supervisor = $this->push->messages()->firstWhere('userId', $this->supervisor->id);
    expect($crew->type)->toBe('ticket_stopped')
        ->and($crew->title)->toBe('Stop work')
        ->and($crew->urgent)->toBeTrue()
        ->and($supervisor->urgent)->toBeFalse();
});

test('a returned report is an urgent alert for the crew', function () {
    givePhone($this->personnel);
    $ticket = deptTicket($this, ['team_id' => $this->team->id, 'status' => TicketStatus::IN_PROGRESS]);
    $report = TicketAccomplishment::factory()->create(['ticket_id' => $ticket->id, 'status' => TicketAccomplishmentStatus::REJECTED]);

    event(new TicketReportRejected($ticket, $report));

    $returned = $this->push->ofType('report_returned');
    expect($returned)->toHaveCount(1)->and($returned[0]->urgent)->toBeTrue();
});

test('a verified and closed ticket tells the crew', function () {
    givePhone($this->personnel);

    event(new TicketVerifiedAndClosed(deptTicket($this, ['team_id' => $this->team->id, 'status' => TicketStatus::CLOSED])));

    expect($this->push->ofType('ticket_closed'))->toHaveCount(1);
});

test('a remark reaches the crew and supervisors but not its author, and internal notes are never pushed', function () {
    givePhone($this->personnel);
    givePhone($this->supervisor);
    $ticket = deptTicket($this, ['team_id' => $this->team->id, 'status' => TicketStatus::IN_PROGRESS]);

    $remark = TicketRemark::create(['ticket_id' => $ticket->id, 'user_id' => $this->supervisor->id, 'body' => 'Bring a ladder.', 'is_internal' => false]);
    event(new TicketRemarkAdded($remark));

    expect($this->push->ofType('remark_added')->pluck('userId')->all())->toBe([$this->personnel->id])
        ->and($this->push->messages()->first()->body)->not->toContain('ladder');

    $internal = TicketRemark::create(['ticket_id' => $ticket->id, 'user_id' => $this->cwdOfficer->id, 'body' => 'Internal note.', 'is_internal' => true]);
    event(new TicketRemarkAdded($internal));

    expect($this->push->messages())->toHaveCount(1);
});

test('a crew member added to a team is told about it', function () {
    givePhone($this->personnel);

    event(new TeamUpdated($this->team, 'updated', addedMemberIds: [$this->personnel->id]));

    $membership = $this->push->ofType('team_membership');
    expect($membership)->toHaveCount(1)
        ->and($membership[0]->body)->toContain($this->team->team_name)
        ->and($membership[0]->data['ticket_id'])->toBe('');
});

test('a rejected endorsement tells the supervisors of the original department', function () {
    givePhone($this->supervisor);
    $ticket = deptTicket($this, ['team_id' => $this->team->id, 'status' => TicketStatus::ASSIGNED]);
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $ticket->id,
        'old_department_id' => $this->dept->id,
        'status' => EndorsementStatus::REJECTED,
    ]);

    $this->actingAs($this->cwdOfficer);
    event(new TicketEndorsementDecided($endorsement));

    $decided = $this->push->ofType('endorsement_decided');
    expect($decided)->toHaveCount(1)->and($decided[0]->title)->toBe('Endorsement rejected');
});

// --- ENDORSEMENTS AND WORK STARTED (review gaps A and B) ---

test('an endorsement request puts the ticket on hold with an urgent alert to the crew only', function () {
    givePhone($this->personnel);
    givePhone($this->supervisor);
    $ticket = deptTicket($this, ['team_id' => $this->team->id, 'status' => TicketStatus::ASSIGNED]);
    Sanctum::actingAs($this->supervisor, ['*']);

    app(InitiateEndorsement::class)->execute($ticket, ['reason' => 'Belongs to the metering department.'], $this->supervisor);

    $hold = $this->push->ofType('ticket_on_hold');
    expect($hold)->toHaveCount(1)
        ->and($hold[0]->userId)->toBe($this->personnel->id)
        ->and($hold[0]->title)->toBe('Ticket on hold')
        ->and($hold[0]->urgent)->toBeTrue()
        ->and($hold[0]->channel)->toBe(PushMessage::CHANNEL_DISPATCH)
        ->and($this->push->messages()->where('userId', $this->supervisor->id))->toBeEmpty();
});

test('an endorsement request on an unassigned ticket alerts no crew', function () {
    givePhone($this->personnel);
    $ticket = deptTicket($this, ['status' => TicketStatus::OPEN]);
    Sanctum::actingAs($this->supervisor, ['*']);

    app(InitiateEndorsement::class)->execute($ticket, ['reason' => 'Belongs to the metering department.'], $this->supervisor);

    expect($this->push->ofType('ticket_on_hold'))->toBeEmpty();
});

test('an approved transfer tells the previous crew the ticket is no longer theirs', function () {
    givePhone($this->personnel);
    $newDept = Department::factory()->create(['dept_name' => 'Metering']);
    $ticket = deptTicket($this, ['status' => TicketStatus::ENDORSED, 'team_id' => null]);
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $ticket->id,
        'old_department_id' => $this->dept->id,
        'suggested_department_id' => $newDept->id,
        'new_department_id' => $newDept->id,
        'status' => EndorsementStatus::APPROVED,
    ]);

    $this->actingAs($this->cwdOfficer);
    event(new TicketEndorsementDecided($endorsement, previousTeamId: $this->team->id));

    $away = $this->push->ofType('ticket_reassigned_away');
    expect($away)->toHaveCount(1)
        ->and($away[0]->userId)->toBe($this->personnel->id)
        ->and($away[0]->title)->toBe('Ticket transferred')
        ->and($away[0]->body)->toContain('Metering');
});

test('a rejected transfer tells the crew they can start work again', function () {
    givePhone($this->personnel);
    $ticket = deptTicket($this, ['team_id' => $this->team->id, 'status' => TicketStatus::ASSIGNED]);
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $ticket->id,
        'old_department_id' => $this->dept->id,
        'status' => EndorsementStatus::REJECTED,
    ]);

    $this->actingAs($this->cwdOfficer);
    event(new TicketEndorsementDecided($endorsement));

    $resumed = $this->push->ofType('ticket_resumed');
    expect($resumed)->toHaveCount(1)
        ->and($resumed[0]->userId)->toBe($this->personnel->id)
        ->and($resumed[0]->urgent)->toBeTrue()
        ->and($resumed[0]->channel)->toBe(PushMessage::CHANNEL_DISPATCH);
});

test('a rejected transfer of an unassigned ticket alerts no crew', function () {
    givePhone($this->personnel);
    $ticket = deptTicket($this, ['team_id' => null, 'status' => TicketStatus::OPEN]);
    $endorsement = TicketEndorsement::factory()->create([
        'ticket_id' => $ticket->id,
        'old_department_id' => $this->dept->id,
        'status' => EndorsementStatus::REJECTED,
    ]);

    event(new TicketEndorsementDecided($endorsement));

    expect($this->push->ofType('ticket_resumed'))->toBeEmpty();
});

test('starting work tells the department supervisor but not the crew member who started', function () {
    givePhone($this->supervisor);
    givePhone($this->personnel);
    $ticket = deptTicket($this, ['team_id' => $this->team->id, 'status' => TicketStatus::IN_PROGRESS]);

    $this->actingAs($this->personnel);
    event(new TicketWorkStarted($ticket->fresh(['team'])));

    $started = $this->push->ofType('work_started');
    expect($started)->toHaveCount(1)
        ->and($started[0]->userId)->toBe($this->supervisor->id)
        ->and($started[0]->body)->toBe("{$this->team->team_name} started work on {$ticket->ticket_number}.")
        ->and($started[0]->urgent)->toBeFalse();
});

test('a supervisor without assign or verify permission is not told work started', function () {
    givePhone($this->supervisor);
    $keep = array_values(array_diff(PermissionCatalog::forRole('supervisor'), ['tickets.assign', 'reports.verify']));
    app(UpdateUserPermissions::class)->execute($this->supervisor, $keep, $this->admin);

    event(new TicketWorkStarted(deptTicket($this, ['team_id' => $this->team->id, 'status' => TicketStatus::IN_PROGRESS])));

    expect($this->push->ofType('work_started'))->toBeEmpty();
});

// --- EXPIRED LOGINS (review issue 4) ---

function ageLogin(PushDevice $device, int $days): void
{
    $device->accessToken->forceFill(['created_at' => now()->subDays($days)])->save();
}

test('a phone whose login expired after 30 days gets no notifications', function () {
    ageLogin(givePhone($this->supervisor), 31);

    event(new TicketCreated(deptTicket($this)));

    expect($this->push->messages())->toBeEmpty();
});

test('a phone whose login is still within 30 days keeps getting notifications', function () {
    ageLogin(givePhone($this->supervisor), 29);

    event(new TicketCreated(deptTicket($this)));

    expect($this->push->messages())->toHaveCount(1);
});

test('a phone whose login has its own past expiry date gets no notifications', function () {
    givePhone($this->supervisor)->accessToken->forceFill(['expires_at' => now()->subMinute()])->save();

    event(new TicketCreated(deptTicket($this)));

    expect($this->push->messages())->toBeEmpty();
});

test('only the phone with a valid login is notified when an account has an expired one too', function () {
    $old = givePhone($this->supervisor);
    ageLogin($old, 31);
    $login = $this->supervisor->createToken('New phone');
    PushDevice::create([
        'user_id' => $this->supervisor->id,
        'personal_access_token_id' => $login->accessToken->id,
        'fcm_token' => 'fcm-token-new-phone-0123456789',
    ]);

    event(new TicketCreated(deptTicket($this)));

    expect($this->push->messages()->pluck('token')->all())->toBe(['fcm-token-new-phone-0123456789']);
});

test('push:test skips a phone whose login expired', function () {
    ageLogin(givePhone($this->personnel), 31);

    $this->artisan('push:test', ['username' => 'ralph'])
        ->expectsOutputToContain('no registered phone with a valid login')
        ->assertFailed();

    expect($this->push->messages())->toBeEmpty();
});

test('the person who caused an event is never notified about it', function () {
    givePhone($this->supervisor);

    $this->actingAs($this->supervisor);
    event(new TicketCreated(deptTicket($this)));

    expect($this->push->messages())->toBeEmpty();
});

test('deactivated accounts and accounts without a phone are skipped', function () {
    givePhone($this->personnel);
    $this->personnel->update(['is_active' => false]);

    event(new TicketCreated(deptTicket($this, ['team_id' => $this->team->id])));
    event(new TicketVerifiedAndClosed(deptTicket($this, ['team_id' => $this->team->id, 'status' => TicketStatus::CLOSED])));

    expect($this->push->messages())->toBeEmpty();
});

test('notifications never carry consumer details', function () {
    givePhone($this->supervisor);
    $consumer = Consumer::factory()->create(['name' => 'Juan Dela Cruz', 'address' => 'Secret Street 12']);
    $ticket = deptTicket($this, ['consumer_id' => $consumer->id, 'consumer_contact' => '09171234567']);

    event(new TicketCreated($ticket));

    $payload = json_encode($this->push->messages()->map->toFcm()->all());
    expect($payload)->not->toContain('Juan Dela Cruz')
        ->not->toContain('Secret Street')
        ->not->toContain('09171234567')
        ->toContain($ticket->ticket_number);
});

test('a failure to reach firebase never breaks the action', function () {
    givePhone($this->personnel);
    $this->push->failing = true;
    $ticket = deptTicket($this, ['status' => TicketStatus::OPEN]);
    Sanctum::actingAs($this->supervisor, ['*']);

    $this->postJson("/api/v1/tickets/{$ticket->id}/assign", ['team_id' => $this->team->id])->assertOk();

    expect($ticket->fresh()->status)->toBe(TicketStatus::ASSIGNED);
});

// --- FIREBASE CLIENT ---

function configureFcm(): FcmClient
{
    config(['services.fcm.project_id' => 'paleco-otrs', 'services.fcm.credentials' => base64_encode('{"type":"service_account"}')]);
    // A cached access token means no call to Google's sign-in service is needed in tests
    Cache::put(FcmClient::TOKEN_CACHE_KEY, 'test-access-token', now()->addHour());

    return new FcmClient;
}

test('the firebase client posts the v1 message format with the access token', function () {
    $client = configureFcm();
    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/paleco-otrs/messages/1'])]);

    $result = $client->send([new PushMessage(
        token: 'phone-token-abcdefghijklmnop', type: 'ticket_assigned', title: 'New dispatch', body: 'TKT-1 · Power',
        data: ['ticket_id' => 'abc', 'ticket_number' => 'TKT-1'], channel: PushMessage::CHANNEL_DISPATCH, urgent: true, tag: 'ticket-abc',
    )]);

    expect($result['sent'])->toBe(1);
    Http::assertSent(function ($request) {
        $message = $request['message'];

        return $request->url() === 'https://fcm.googleapis.com/v1/projects/paleco-otrs/messages:send'
            && $request->hasHeader('Authorization', 'Bearer test-access-token')
            && $message['token'] === 'phone-token-abcdefghijklmnop'
            && $message['android']['priority'] === 'HIGH'
            && $message['android']['notification']['channel_id'] === 'dispatch'
            && $message['android']['notification']['tag'] === 'ticket-abc'
            && ! array_key_exists('collapse_key', $message['android'])
            && $message['data']['type'] === 'ticket_assigned'
            && $message['data']['ticket_number'] === 'TKT-1'
            && is_string($message['data']['sent_at']);
    });
});

test('a phone that uninstalled the app is removed when firebase reports it', function () {
    $client = configureFcm();
    $device = givePhone($this->personnel);
    Http::fake(['fcm.googleapis.com/*' => Http::response([
        'error' => ['code' => 404, 'status' => 'NOT_FOUND', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]],
    ], 404)]);

    $result = $client->send([new PushMessage(token: $device->fcm_token, type: 'test', title: 'T', body: 'B')]);

    expect($result)->toBe(['sent' => 0, 'failed' => 1, 'removed' => 1])
        ->and(PushDevice::count())->toBe(0);
});

test('an expired access token is forgotten so the next send fetches a new one', function () {
    $client = configureFcm();
    Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['code' => 401, 'status' => 'UNAUTHENTICATED']], 401)]);

    $client->send([new PushMessage(token: 'phone-token-abcdefghijklmnop', type: 'test', title: 'T', body: 'B')]);

    expect(Cache::has(FcmClient::TOKEN_CACHE_KEY))->toBeFalse();
});

test('without firebase settings nothing is sent and nothing fails', function () {
    Http::fake();
    config(['services.fcm.project_id' => null, 'services.fcm.credentials' => null]);

    $result = (new FcmClient)->send([new PushMessage(token: 'phone-token-abcdefghijklmnop', type: 'test', title: 'T', body: 'B')]);

    expect($result)->toBe(['sent' => 0, 'failed' => 0, 'removed' => 0]);
    Http::assertNothingSent();
});

test('the test suite never has real firebase settings', function () {
    expect(app(FcmClient::class)->isConfigured())->toBeFalse();
});

// --- TEST COMMAND ---

test('push:test sends a notification to every phone of the account', function () {
    givePhone($this->personnel);

    $this->artisan('push:test', ['username' => 'ralph'])
        ->expectsOutputToContain('Sent: 1')
        ->assertSuccessful();

    expect($this->push->ofType('test'))->toHaveCount(1);
});

test('push:test explains when the account has no phone', function () {
    $this->artisan('push:test', ['username' => 'ralph'])
        ->expectsOutputToContain('has no registered phone')
        ->assertFailed();
});
