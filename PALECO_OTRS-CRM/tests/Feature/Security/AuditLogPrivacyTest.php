<?php

use App\Actions\Users\ResetUserPassword;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ActivityLogs\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\Seeders\SampleDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SampleDataSeeder::class);
});

test('creating a user does not store personal information in the audit log', function () {
    $user = User::factory()->cwdOfficer()->create([
        'username' => 'mdc_officer',
        'first_name' => 'maria',
        'last_name' => 'delacruz',
        'email' => 'maria.delacruz@paleco.com',
        'contact' => '09181234567',
    ]);

    $activity = Activity::where('subject_id', $user->id)->where('event', 'created')->firstOrFail();
    $raw = DB::table('activity_log')->where('id', $activity->id)->value('attribute_changes');

    expect($raw)->not->toContain('maria')
        ->not->toContain('delacruz@paleco.com')
        ->not->toContain('09181234567')
        ->toContain('mdc_officer');

    expect($activity->attribute_changes['attributes']['contact'])->toBe('[redacted]')
        ->and($activity->attribute_changes['attributes']['email'])->toBe('[redacted]');
});

test('updating a user records which personal fields changed without their values', function () {
    $user = User::factory()->cwdOfficer()->create(['contact' => '09171111111']);

    $user->update(['contact' => '09179999999']);

    $activity = Activity::where('subject_id', $user->id)->where('event', 'updated')->latest('id')->firstOrFail();
    $raw = DB::table('activity_log')->where('id', $activity->id)->value('attribute_changes');

    expect($raw)->not->toContain('09171111111')->not->toContain('09179999999');
    expect($activity->attribute_changes['old']['contact'])->toBe('[redacted]')
        ->and($activity->attribute_changes['attributes']['contact'])->toBe('[redacted]');

    // Audit details page still flags the redacted field as changed
    $contactRow = collect(app(ActivityLogService::class)->getActivityDetail($activity)['diff']['rows'])
        ->firstWhere('key', 'contact');

    expect($contactRow['is_changed'])->toBeTrue()
        ->and($contactRow['new_display'])->toBe('[redacted]');
});

test('ticket consumer contact is redacted in the audit log', function () {
    $ticket = Ticket::factory()->create(['consumer_contact' => '09191234567']);

    $activity = Activity::where('subject_id', $ticket->id)->where('event', 'created')->firstOrFail();
    $raw = DB::table('activity_log')->where('id', $activity->id)->value('attribute_changes');

    expect($raw)->not->toContain('09191234567')
        ->toContain($ticket->ticket_number);
    expect($activity->attribute_changes['attributes']['consumer_contact'])->toBe('[redacted]');
});

test('login audit entries keep the username but no personal identity properties', function () {
    $this->post(route('attemptLogin', ['role' => 'cwd_officer']), [
        'username' => 'alliah',
        'password' => 'Password',
    ]);

    $activity = Activity::where('log_name', 'login_success')->latest('id')->firstOrFail();

    expect($activity->properties['username'])->toBe('alliah')
        ->and($activity->properties->has('full_name'))->toBeFalse()
        ->and($activity->properties->has('email'))->toBeFalse()
        ->and($activity->properties->has('contact'))->toBeFalse();
});

test('administrative password reset audit entry does not store the full name', function () {
    $admin = User::where('username', 'allenglenn')->first();
    $target = User::where('username', 'alliah')->first();
    $this->actingAs($admin);

    app(ResetUserPassword::class)->execute($target, 'NewSecurePass123');

    $activity = Activity::where('event', 'password_reset')->latest('id')->firstOrFail();

    expect($activity->properties['username'])->toBe('alliah')
        ->and($activity->properties->has('full_name'))->toBeFalse();
});
