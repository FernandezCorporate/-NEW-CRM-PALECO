<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds targeted composite and single-column indexes on high-frequency query paths for tickets.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('status', 'idx_tickets_status');
            $table->index('reported_at', 'idx_tickets_reported_at');
            $table->index('created_at', 'idx_tickets_created_at');
            $table->index('closed_at', 'idx_tickets_closed_at');
            $table->index(['department_id', 'status'], 'idx_tickets_department_status');
            $table->index(['team_id', 'status'], 'idx_tickets_team_status');
            $table->index(['status', 'reported_at'], 'idx_tickets_status_reported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('idx_tickets_status');
            $table->dropIndex('idx_tickets_reported_at');
            $table->dropIndex('idx_tickets_created_at');
            $table->dropIndex('idx_tickets_closed_at');
            $table->dropIndex('idx_tickets_department_status');
            $table->dropIndex('idx_tickets_team_status');
            $table->dropIndex('idx_tickets_status_reported_at');
        });
    }
};
