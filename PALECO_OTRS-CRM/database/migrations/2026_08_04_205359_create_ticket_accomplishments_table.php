<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_accomplishments', function (Blueprint $table) {
            $table->id(); 
            $table->char('ticket_id', 26); 
            $table->ulid('accomplished_by_id'); 
            
            // Job Accomplishment fields
            $table->text('remarks'); 
            $table->timestamp('accomplished_at'); 
            
            // Acknowledgement fields
            $table->string('consumer_name')->nullable();
            $table->string('signature_path')->nullable(); 

            // --- THE NEW VERIFICATION COLUMNS ---
            // Tracks if this specific report is pending, approved, or rejected
            $table->string('status')->default('pending'); 
            
            // Allows the Foreman to tell the worker exactly WHY it was rejected
            $table->ulid('rejected_by_id')->nullable();
            $table->text('rejection_reason')->nullable(); 
            
            $table->timestamps();

            $table->foreign('ticket_id')->references('system_id')->on('tickets')->cascadeOnDelete();
            $table->foreign('accomplished_by_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('rejected_by_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_accomplishments');
    }
};