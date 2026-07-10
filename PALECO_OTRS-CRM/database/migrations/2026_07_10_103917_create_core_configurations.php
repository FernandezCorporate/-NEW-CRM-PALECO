<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Departments (Final state: no unique constraint on dept_name)[cite: 19, 20]
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('dept_name'); 
            $table->string('dept_desc')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Account Roles (Final state: renamed, no desc, no soft deletes)[cite: 25, 28, 29]
        Schema::create('account_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name')->unique();
            $table->string('slug_identifier')->unique();
            $table->timestamps();
        });

        // Team Roles lookup[cite: 30]
        Schema::create('team_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name');
            $table->string('slug_identifier')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_roles');
        Schema::dropIfExists('account_roles');
        Schema::dropIfExists('departments');
    }
};