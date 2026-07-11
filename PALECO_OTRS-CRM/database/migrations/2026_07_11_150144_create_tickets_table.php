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
        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('system_id')->primary();
            $table->string('ticket_number');

            $table->string('purok')->nullable();
            $table->string('street')->nullable();
            $table->string('barangay');
            $table->string('landmark')->nullable();

            $table->string('complaint_source');
            $table->foreignId('category_id')->constrained('ticket_categories');
            $table->text('complaint_description')->nullable();

            $table->foreignId('department_id')->nullable()->constrained();
            $table->foreignUlid('created_by')->constrained('users');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
