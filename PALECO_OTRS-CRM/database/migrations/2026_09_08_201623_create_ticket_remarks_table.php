<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_remarks', function (Blueprint $table) {
            $table->ulid('system_id')->primary();
            
            // Foreign key referencing Ticket ULID (system_id)
            $table->foreignUlid('ticket_id')
                  ->constrained('tickets', 'system_id')
                  ->cascadeOnDelete();

            // Foreign key referencing User ULID (id)
            $table->foreignUlid('user_id')
                  ->constrained('users', 'id')
                  ->cascadeOnDelete();

            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_remarks');
    }
};