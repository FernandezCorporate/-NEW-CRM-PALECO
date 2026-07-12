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
        // 1. Create the Main Tickets Table
        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('system_id')->primary();
            $table->string('ticket_number')->unique(); 
            
            // Self-referencing link: local 'parent_ticket_id' -> tickets.system_id
            $table->foreignUlid('parent_ticket_id')
                ->nullable()
                ->constrained('tickets', 'system_id') 
                ->nullOnDelete();
            
            $table->string('consumer_id')->nullable();

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
            
            // FIXED: Point to the 'id' column on the 'users' table
            $table->foreignUlid('created_by')->constrained('users', 'id');
            
            $table->string('status')->default('open');
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 2. Create the Status Logs Table (Audit Trail)
        Schema::create('ticket_status_logs', function (Blueprint $table) {
            $table->id();
            
            // Local column 'ticket_id' links to tickets.system_id
            $table->foreignUlid('ticket_id')
                  ->constrained('tickets', 'system_id')
                  ->cascadeOnDelete();

            // FIXED: Point to the 'id' column on the 'users' table
            $table->foreignUlid('changed_by')
                  ->nullable()
                  ->constrained('users', 'id')
                  ->nullOnDelete();

            $table->string('old_status')->nullable(); 
            $table->string('new_status');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_status_logs');
        Schema::dropIfExists('tickets');
    }
};
