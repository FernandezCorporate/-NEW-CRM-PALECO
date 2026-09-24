<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name'); 
            $table->string('category_desc')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('system_id')->primary();
            $table->string('ticket_number')->unique(); 
            
            $table->foreignUlid('parent_ticket_id')->nullable()->constrained('tickets', 'system_id')->nullOnDelete();
            $table->foreignUlid('consumer_id')->nullable()->constrained('consumers', 'id')->nullOnDelete();

            $table->string('complaint_source');
            $table->text('complaint_description')->nullable();
            
            $table->foreignId('category_id')->nullable()->constrained('ticket_categories');
            $table->boolean('other_category')->default(false);
            $table->string('other_category_name')->nullable();

            $table->string('purok')->nullable();
            $table->string('street')->nullable();
            $table->string('barangay');
            $table->string('landmark')->nullable();

            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignUlid('team_id')->nullable()->constrained('teams');
            
            $table->foreignUlid('created_by')->constrained('users', 'id');
            
            $table->string('status')->default('open');
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('ticket_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('ticket_id')->constrained('tickets', 'system_id')->cascadeOnDelete();
            $table->foreignUlid('changed_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->string('old_status')->nullable(); 
            $table->string('new_status');
            $table->timestamps();
        });

        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->char('ticket_id', 26);
            $table->char('team_id', 26);
            $table->ulid('assigned_by');
            $table->text('reason')->nullable();
            
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('system_id')->on('tickets')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->cascadeOnDelete();
        });

        // --- UPDATED TABLE ---
        Schema::create('ticket_endorsements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            $table->char('ticket_id', 26);
            $table->foreign('ticket_id')->references('system_id')->on('tickets')->cascadeOnDelete();
            
            $table->char('created_by', 26);
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            
            $table->unsignedBigInteger('suggested_department_id')->nullable();
            
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->string('pre_endorsement_status'); // UPDATED COLUMN
            
            $table->text('rejection_reason')->nullable();
            $table->char('reviewed_by', 26)->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            
            $table->timestamps();
        });

        Schema::create('ticket_accomplishments', function (Blueprint $table) {
            $table->id(); 
            $table->char('ticket_id', 26); 
            $table->ulid('accomplished_by_id'); 
            
            $table->text('remarks'); 
            $table->timestamp('accomplished_at'); 
            
            $table->string('consumer_name')->nullable();
            $table->string('signature_path')->nullable(); 

            $table->string('status')->default('pending'); 
            $table->char('approved_by_id', 26)->nullable();
            
            $table->ulid('rejected_by_id')->nullable();
            $table->text('rejection_reason')->nullable(); 
            
            $table->timestamps();

            $table->foreign('ticket_id')->references('system_id')->on('tickets')->cascadeOnDelete();
            $table->foreign('accomplished_by_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('rejected_by_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('accomplishment_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accomplishment_id')->constrained('ticket_accomplishments')->cascadeOnDelete(); 
            $table->string('file_path');
            $table->timestamps();
        });

        Schema::create('ticket_remarks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ticket_id')->constrained('tickets', 'system_id')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users', 'id')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_remarks');
        Schema::dropIfExists('accomplishment_photos');
        Schema::dropIfExists('ticket_accomplishments');
        Schema::dropIfExists('ticket_endorsements'); // UPDATED
        Schema::dropIfExists('ticket_assignments');
        Schema::dropIfExists('ticket_status_logs');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_categories');
    }
};