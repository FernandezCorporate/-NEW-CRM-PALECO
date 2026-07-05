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
        Schema::create('teams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('team_name')->unique();
            $table->string('team_desc')->nullable();
            $table->time('shift_start');
            $table->time('shift_end');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
