<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Teams (Final state: no unique constraint on team_name)[cite: 22, 24]
        Schema::create('teams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('team_name'); 
            $table->string('team_desc')->nullable();
            $table->time('shift_start');
            $table->time('shift_end');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        // Pivot table linking Users, Teams, and Team Roles[cite: 23, 30]
        Schema::create('team_members', function (Blueprint $table) {
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_role_id')->constrained('team_roles');
            $table->primary(['user_id', 'team_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};