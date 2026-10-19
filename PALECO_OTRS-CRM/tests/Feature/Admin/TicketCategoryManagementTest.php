<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('admin can view ticket categories index', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.ticketCategories'));

    $response->assertStatus(200);
    $response->assertViewIs('admin.pages.ticketCategoryManagement');
    $response->assertViewHas('categories');
});

test('non-admin users cannot access ticket categories', function () {
    $cwdOfficer = User::factory()->cwdOfficer()->create();
    $supervisor = User::factory()->supervisor()->create();
    $fieldPersonnel = User::factory()->fieldPersonnel()->create();

    $this->actingAs($cwdOfficer)->get(route('admin.ticketCategories'))
        ->assertStatus(404);

    $this->actingAs($supervisor)->get(route('admin.ticketCategories'))
        ->assertStatus(404);

    $this->actingAs($fieldPersonnel)->get(route('admin.ticketCategories'))
        ->assertStatus(404);
});

test('admin can view category details and forms', function () {
    $admin = User::factory()->admin()->create();
    $category = TicketCategory::first();

    $this->actingAs($admin)->get(route('admin.ticketCategories.show', $category))
        ->assertStatus(200)
        ->assertViewIs('admin.pages.ticketCategoryDetails')
        ->assertViewHas('category');

    $this->actingAs($admin)->get(route('admin.ticketCategories.createForm'))
        ->assertStatus(200)
        ->assertViewIs('admin.forms.ticketCategoryForm');

    $this->actingAs($admin)->get(route('admin.ticketCategories.editForm', $category))
        ->assertStatus(200)
        ->assertViewIs('admin.forms.ticketCategoryForm');
});

test('attempting to edit an archived category redirects with error', function () {
    $admin = User::factory()->admin()->create();
    $category = TicketCategory::factory()->create();
    $category->delete();

    $response = $this->actingAs($admin)->get(route('admin.ticketCategories.editForm', $category));

    $response->assertRedirect(route('admin.ticketCategories'));
    $response->assertSessionHas('error', 'The category you are trying to edit has been archived by another administrator.');
});

test('admin can create a new ticket category', function () {
    $admin = User::factory()->admin()->create();

    $payload = [
        'category_name' => 'Transformer Arc Flash Hazard',
        'category_desc' => 'High voltage electrical arc flash and equipment fires.',
    ];

    $response = $this->actingAs($admin)->post(route('admin.ticketCategories.store'), $payload);

    $response->assertRedirect(route('admin.ticketCategories'));
    $response->assertSessionHas('success', 'Ticket category created successfully.');

    $this->assertDatabaseHas('ticket_categories', [
        'category_name' => 'Transformer Arc Flash Hazard',
    ]);
});

test('category creation fails when name is missing or duplicated', function () {
    $admin = User::factory()->admin()->create();
    $existing = TicketCategory::first();

    // Duplicate
    $response = $this->actingAs($admin)->post(route('admin.ticketCategories.store'), [
        'category_name' => $existing->category_name,
    ]);
    $response->assertSessionHasErrors(['category_name']);

    // Missing
    $response = $this->actingAs($admin)->post(route('admin.ticketCategories.store'), [
        'category_name' => '',
    ]);
    $response->assertSessionHasErrors(['category_name']);
});

test('admin can update ticket category with concurrency checking', function () {
    $admin = User::factory()->admin()->create();
    $category = TicketCategory::factory()->create([
        'category_name' => 'Initial Category Name',
    ]);

    $payload = [
        'category_name' => 'Updated Category Title',
        'category_desc' => 'Updated category classification notes.',
        'original_updated_at' => $category->updated_at->toIso8601String(),
    ];

    $response = $this->actingAs($admin)->put(route('admin.ticketCategories.update', $category), $payload);

    $response->assertRedirect(route('admin.ticketCategories'));
    $response->assertSessionHas('success', 'Ticket category updated successfully.');

    expect($category->fresh()->category_name)->toBe('Updated Category Title');
});

test('ticket category update fails on concurrency timestamp mismatch', function () {
    $admin = User::factory()->admin()->create();
    $category = TicketCategory::factory()->create();

    $staleTimestamp = $category->updated_at->subMinutes(5)->toIso8601String();

    $payload = [
        'category_name' => 'Collision Category Name',
        'original_updated_at' => $staleTimestamp,
    ];

    $response = $this->actingAs($admin)->put(route('admin.ticketCategories.update', $category), $payload);

    $response->assertSessionHas('error');
    expect($category->fresh()->category_name)->not->toBe('Collision Category Name');
});

test('admin can archive, restore, and force-delete an unassigned ticket category', function () {
    $admin = User::factory()->admin()->create();
    $category = TicketCategory::factory()->create();

    // Confirm prompt
    $this->actingAs($admin)->get(route('admin.ticketCategories.deleteConfirm', $category))
        ->assertStatus(200)
        ->assertViewIs('admin.prompts.ticketCategoryDeleteConfirm');

    // Archive
    $archiveResponse = $this->actingAs($admin)->delete(route('admin.ticketCategories.archive', $category));
    $archiveResponse->assertRedirect(route('admin.ticketCategories'));
    $archiveResponse->assertSessionHas('success', 'Category archived successfully.');
    expect($category->fresh()->trashed())->toBeTrue();

    // Restore
    $restoreResponse = $this->actingAs($admin)->patch(route('admin.ticketCategories.restore', $category));
    $restoreResponse->assertRedirect(route('admin.ticketCategories'));
    $restoreResponse->assertSessionHas('success', 'Category restored successfully.');
    expect($category->fresh()->trashed())->toBeFalse();

    // Re-archive and permanently force delete
    $this->actingAs($admin)->delete(route('admin.ticketCategories.archive', $category));
    $destroyResponse = $this->actingAs($admin)->delete(route('admin.ticketCategories.destroy', $category));
    $destroyResponse->assertRedirect(route('admin.ticketCategories'));
    $destroyResponse->assertSessionHas('success', 'Category permanently deleted.');
    $this->assertDatabaseMissing('ticket_categories', ['id' => $category->id]);
});

test('archiving ticket category is blocked when active tickets are linked', function () {
    $admin = User::factory()->admin()->create();
    $category = TicketCategory::factory()->create();

    Ticket::factory()->create([
        'category_id' => $category->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.ticketCategories.archive', $category));

    $response->assertRedirect(route('admin.ticketCategories'));
    $response->assertSessionHas('error', 'Cannot archive this category because it is currently assigned to active service tickets.');
    expect($category->fresh()->trashed())->toBeFalse();
});
