<?php

use App\Services\Web\Dashboard\DashboardService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // A dedicated in-memory connection: never migrate or alter the local application database.
    config(['database.default' => 'dashboard_test', 'database.connections.dashboard_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    Schema::create('tickets', function (Blueprint $table) {
        $table->string('system_id')->primary();
        $table->string('status');
        $table->integer('department_id')->nullable();
        $table->integer('team_id')->nullable();
        $table->timestamp('created_at');
        $table->timestamp('closed_at')->nullable();
        $table->softDeletes();
    });
    Schema::create('departments', function (Blueprint $table) {
        $table->id();
        $table->string('dept_name');
        $table->softDeletes();
    });
    foreach (['ticket_escalations', 'ticket_accomplishments'] as $name) {
        Schema::create($name, function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id');
            $table->string('status');
        });
    }
    Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
    DB::purge('dashboard_test');
});

test('an empty dashboard has safe zero counts and chart scales', function () {
    $data = app(DashboardService::class)->operationsSnapshot();
    expect($data['active'])->toBe(0)
        ->and($data['received_today'])->toBe(0)
        ->and($data['departments'])->toBe([])
        ->and($data['department_max'])->toBe(1)
        ->and(array_sum(array_column($data['aging'], 'total')))->toBe(0);
});

test('operations count unresolved tickets and exact age boundaries without deleted records', function () {
    DB::table('departments')->insert(['id' => 1, 'dept_name' => 'Field services', 'deleted_at' => now()]);
    foreach ([
        ['new', 'open', now(), null],
        ['one-day', 'assigned', now()->subDay(), 1],
        ['seven-days', 'escalated', now()->subDays(7), 1],
        ['resolved', 'resolved', now()->subDays(9), 1],
        ['closed', 'closed', now()->subDays(9), 1],
        ['deleted', 'open', now()->subDays(9), 1],
    ] as [$id, $status, $date, $department]) {
        DB::table('tickets')->insert([
            'system_id' => $id, 'status' => $status, 'created_at' => $date,
            'department_id' => $department, 'team_id' => $id === 'one-day' ? 1 : null,
            'closed_at' => $id === 'closed' ? now() : null,
            'deleted_at' => $id === 'deleted' ? now() : null,
        ]);
    }
    foreach (['ticket_escalations', 'ticket_accomplishments'] as $table) {
        DB::table($table)->insert([
            ['ticket_id' => 'new', 'status' => 'pending'],
            ['ticket_id' => 'deleted', 'status' => 'pending'],
            ['ticket_id' => 'new', 'status' => 'approved'],
        ]);
    }
    $data = app(DashboardService::class)->operationsSnapshot();
    expect($data['active'])->toBe(3)
        ->and($data['without_team'])->toBe(2)
        ->and($data['received_today'])->toBe(1)
        ->and($data['closed_today'])->toBe(1)
        ->and($data['pending_escalations'])->toBe(1)
        ->and($data['pending_reports'])->toBe(1)
        ->and(array_column($data['aging'], 'total'))->toBe([1, 1, 1])
        ->and($data['departments'][0])->toBe(['label' => 'Field services (archived)', 'total' => 2])
        ->and($data['departments'][1])->toBe(['label' => 'No department', 'total' => 1]);
});
