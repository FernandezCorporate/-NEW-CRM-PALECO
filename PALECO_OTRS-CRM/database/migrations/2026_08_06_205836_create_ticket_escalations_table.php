<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_escalations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // Link to the parent ticket
            $table->char('ticket_id', 26);
            $table->foreign('ticket_id')->references('system_id')->on('tickets')->cascadeOnDelete();
            
            // The Foreman's inputs
            $table->unsignedBigInteger('suggested_department_id')->nullable();
            $table->foreign('suggested_department_id')->references('id')->on('departments')->nullOnDelete();
            $table->text('reason');
            
            // State management
            $table->string('status')->default('pending');
            $table->string('pre_escalation_status'); // Stores the status to rebound to if rejected
            
            // The CWD's inputs
            $table->text('rejection_reason')->nullable();
            $table->char('reviewed_by', 26)->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_escalations');
    }
};