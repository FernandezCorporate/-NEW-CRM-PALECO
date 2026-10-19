<?php

use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketRemark;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->cwdOfficer = User::where('username', 'alliah')->first();
    $this->admin = User::where('username', 'allenglenn')->first();
    $this->supervisor = User::where('username', 'mycka')->first();
    $this->personnel = User::where('username', 'ralph')->first();

    $this->dept = Department::first();
    $this->supervisor->update(['department_id' => $this->dept->id]);

    $this->team = Team::where('department_id', $this->dept->id)->first() ?? Team::first();
    $this->category = TicketCategory::first();

    // Attach personnel to team
    if (! $this->team->members->contains($this->personnel->id)) {
        $this->team->members()->attach($this->personnel->id, ['team_role_id' => 1]);
    }

    $this->ticket = Ticket::factory()->assigned($this->team)->create([
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
    ]);
});

test('cwd officer can add a remark on ticket via web portal', function () {
    $response = $this->actingAs($this->cwdOfficer)
        ->post(route('shared.tickets.remarks.store', ['ticket' => $this->ticket]), [
            'body' => 'Spoke with consumer via hotline; confirmed crew is on the way.',
            'is_internal' => false,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $remark = TicketRemark::where('ticket_id', $this->ticket->id)->first();
    expect($remark)->not->toBeNull()
        ->and($remark->user_id)->toBe($this->cwdOfficer->id)
        ->and($remark->body)->toContain('Spoke with consumer');
});

test('admin cannot add a remark via web portal', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('shared.tickets.remarks.store', ['ticket' => $this->ticket]), [
            'body' => 'Admin trying to post remark.',
        ]);

    $response->assertForbidden();
});

test('supervisor in same department can post remark via mobile api', function () {
    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/remarks", [
        'body' => 'Technical assessment confirms transformer replacement needed.',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.body', 'Technical assessment confirms transformer replacement needed.');
});

test('supervisor in different department cannot post remark via mobile api', function () {
    $otherDept = Department::where('id', '!=', $this->dept->id)->first() ?? Department::factory()->create();
    $this->supervisor->update(['department_id' => $otherDept->id]);

    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/remarks", [
        'body' => 'Out-of-department supervisor posting remark.',
    ]);

    $response->assertNotFound();
});

test('field personnel on assigned team can post remark via mobile api', function () {
    Sanctum::actingAs($this->personnel, ['*']);

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/remarks", [
        'body' => 'Crew arrived at location, beginning wire clearing.',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('success', true);
});

test('field personnel not on assigned team cannot post remark via mobile api', function () {
    // Detach personnel from team
    $this->team->members()->detach($this->personnel->id);

    Sanctum::actingAs($this->personnel, ['*']);

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/remarks", [
        'body' => 'Unassigned personnel posting remark.',
    ]);

    $response->assertNotFound();
});

test('mobile user can retrieve timeline of remarks for a ticket', function () {
    TicketRemark::create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->supervisor->id,
        'body' => 'First milestone update.',
        'is_internal' => false,
    ]);

    Sanctum::actingAs($this->personnel, ['*']);

    $response = $this->getJson("/api/v1/tickets/{$this->ticket->id}/remarks");

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.body'))->toBe('First milestone update.');
});

test('empty remark body is rejected by validation', function () {
    Sanctum::actingAs($this->supervisor, ['*']);

    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/remarks", [
        'body' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['body']);
});

