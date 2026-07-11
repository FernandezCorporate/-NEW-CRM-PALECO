<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create the Main Tickets Table[cite: 8]
        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('system_id')->primary();
            $table->string('ticket_number')->unique(); 
            
            $table->string('consumer_id')->nullable()->comment('Future reference for external Consumer API');

            $table->string('purok')->nullable();
            $table->string('street')->nullable();
            $table->string('barangay');
            $table->string('landmark')->nullable();

            $table->string('complaint_source');
            $table->text('complaint_description')->nullable();

            $table->foreignId('category_id')->nullable()->constrained('ticket_categories');
            $table->boolean('other_category')->default(false);
            $table->string('other_category_name')->nullable();

            $table->foreignId('department_id')->nullable()->constrained();
            $table->foreignUlid('created_by')->constrained('users');
            
            $table->string('status')->default('open');

            $table->foreignUlid('parent_ticket_id')
                ->nullable()
                ->constrained('tickets', 'system_id')
                ->nullOnDelete();
            
            $table->timestamp('reported_at')->nullable()->comment('Exact time the complaint was received');
            $table->timestamp('resolved_at')->nullable()->comment('Exact time the ticket was fully closed');

            $table->softDeletes();
            $table->timestamps();
        });

        // 2. Create the Status Logs Table (Audit Trail)
        Schema::create('ticket_status_logs', function (Blueprint $table) {
            $table->id();
            
            // Link to the main ticket table
            $table->foreignUlid('ticket_id')
                  ->constrained('tickets', 'system_id')
                  ->cascadeOnDelete();

            // Who performed the action
            $table->foreignUlid('changed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->string('old_status')->nullable(); 
            $table->string('new_status');
            
            $table->string('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Must drop the child table (logs) before the parent table (tickets)
        Schema::dropIfExists('ticket_status_logs');
        Schema::dropIfExists('tickets');
    }
};