<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id('id')->primary();
            
            // Link to the ticket and the assigned team
            $table->char('ticket_id', 26);
            $table->char('team_id', 26);
            
            // Link to the Foreman who made the assignment
            $table->ulid('assigned_by');

            // Timestamps for SLA math (created_at acts as assigned_at)
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('ticket_id')->references('system_id')->on('tickets')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /*
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_assignments');
    }
};