<?php

use App\Actions\Categories\ArchiveTicketCategory;
use App\Actions\Categories\CreateTicketCategory;
use App\Actions\Categories\ForceDeleteTicketCategory;
use App\Actions\Categories\RestoreTicketCategory;
use App\Actions\Categories\UpdateTicketCategory;
use App\Actions\Departments\ArchiveDepartment;
use App\Actions\Departments\CreateDepartment;
use App\Actions\Departments\ForceDeleteDepartment;
use App\Actions\Departments\RestoreDepartment;
use App\Actions\Departments\UpdateDepartment;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('CreateDepartment stores new department and UpdateDepartment respects concurrency', function () {
    $createAction = app(CreateDepartment::class);
    $dept = $createAction->execute([
        'dept_name' => 'Substation Maintenance Group',
        'dept_desc' => 'Responsible for high-voltage substation switchgear.',
    ]);

    expect($dept)->toBeInstanceOf(Department::class);
    expect($dept->dept_name)->toBe('Substation Maintenance Group');

    // Concurrency collision test
    $updateAction = app(UpdateDepartment::class);
    $staleTimestamp = $dept->updated_at->subMinutes(5)->toIso8601String();

    $collisionResult = $updateAction->execute($dept, [
        'dept_name' => 'Colliding Name',
        'original_updated_at' => $staleTimestamp,
    ]);

    expect($collisionResult['success'])->toBeFalse();
    expect($collisionResult['message'])->toContain('Conflict: Another administrator modified this department');

    // Successful update
    $validResult = $updateAction->execute($dept, [
        'dept_name' => 'Primary Substation Maintenance Group',
        'original_updated_at' => $dept->updated_at->toIso8601String(),
    ]);

    expect($validResult['success'])->toBeTrue();
    expect($validResult['changed'])->toBeTrue();
    expect($dept->fresh()->dept_name)->toBe('Primary Substation Maintenance Group');
});

test('ArchiveDepartment blocks archival when active teams or tickets exist', function () {
    $dept = Department::factory()->create();
    Team::factory()->create(['department_id' => $dept->id]);

    $archiveAction = app(ArchiveDepartment::class);
    $result = $archiveAction->execute($dept);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('Cannot archive this department');
    expect($dept->fresh()->trashed())->toBeFalse();
});

test('Department lifecycle handles archive, restore, and force delete', function () {
    $dept = Department::factory()->create();

    $archiveAction = app(ArchiveDepartment::class);
    $archiveResult = $archiveAction->execute($dept);
    expect($archiveResult['success'])->toBeTrue();
    expect($dept->fresh()->trashed())->toBeTrue();

    $restoreAction = app(RestoreDepartment::class);
    $restoreResult = $restoreAction->execute($dept->id);
    expect($restoreResult['success'])->toBeTrue();
    expect($dept->fresh()->trashed())->toBeFalse();

    // Re-archive and permanently force delete
    $dept->delete();
    $forceDeleteAction = app(ForceDeleteDepartment::class);
    $forceResult = $forceDeleteAction->execute($dept->id);
    expect($forceResult['success'])->toBeTrue();
    $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
});

test('CreateTicketCategory stores category and UpdateTicketCategory checks concurrency', function () {
    $createAction = app(CreateTicketCategory::class);
    $category = $createAction->execute([
        'category_name' => 'High Wind Overhead Line Clashing',
        'category_desc' => 'Conductors clashing during typhoons.',
    ]);

    expect($category)->toBeInstanceOf(TicketCategory::class);
    expect($category->category_name)->toBe('High Wind Overhead Line Clashing');

    $updateAction = app(UpdateTicketCategory::class);
    $staleTimestamp = $category->updated_at->subMinutes(5)->toIso8601String();

    $collisionResult = $updateAction->execute($category, [
        'category_name' => 'Colliding Category',
        'original_updated_at' => $staleTimestamp,
    ]);

    expect($collisionResult['success'])->toBeFalse();

    $validResult = $updateAction->execute($category, [
        'category_name' => 'Typhoon Overhead Line Clashing',
        'original_updated_at' => $category->updated_at->toIso8601String(),
    ]);

    expect($validResult['success'])->toBeTrue();
    expect($validResult['changed'])->toBeTrue();
    expect($category->fresh()->category_name)->toBe('Typhoon Overhead Line Clashing');
});

test('ArchiveTicketCategory blocks archival when active tickets exist', function () {
    $category = TicketCategory::factory()->create();
    Ticket::factory()->create([
        'category_id' => $category->id,
        'status' => TicketStatus::OPEN->value,
    ]);

    $archiveAction = app(ArchiveTicketCategory::class);
    $result = $archiveAction->execute($category);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('Cannot archive this category');
    expect($category->fresh()->trashed())->toBeFalse();
});

test('TicketCategory lifecycle handles archive, restore, and force delete', function () {
    $category = TicketCategory::factory()->create();

    $archiveAction = app(ArchiveTicketCategory::class);
    $archiveResult = $archiveAction->execute($category);
    expect($archiveResult['success'])->toBeTrue();
    expect($category->fresh()->trashed())->toBeTrue();

    $restoreAction = app(RestoreTicketCategory::class);
    $restoreResult = $restoreAction->execute($category->id);
    expect($restoreResult['success'])->toBeTrue();
    expect($category->fresh()->trashed())->toBeFalse();

    // Re-archive and permanently force delete
    $category->delete();
    $forceDeleteAction = app(ForceDeleteTicketCategory::class);
    $forceResult = $forceDeleteAction->execute($category->id);
    expect($forceResult['success'])->toBeTrue();
    $this->assertDatabaseMissing('ticket_categories', ['id' => $category->id]);
});

