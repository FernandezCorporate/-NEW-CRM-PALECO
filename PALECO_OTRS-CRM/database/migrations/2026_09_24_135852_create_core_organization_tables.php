<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('dept_name'); 
            $table->string('dept_desc')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('account_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name')->unique();
            $table->string('slug_identifier')->unique();
            $table->timestamps();
        });

        Schema::create('team_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name');
            $table->string('slug_identifier')->unique();
            $table->timestamps();
        });

        Schema::create('consumers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('acct_no')->unique(); 
            $table->string('acct_code')->unique(); 
            $table->string('name');
            $table->string('address');
            $table->string('status')->nullable();
            $table->string('meter_serial')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('username')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('name_ext')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('contact');
            $table->foreignId('role_id')->nullable()->constrained('account_roles')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamp('last_login')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });

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
        Schema::dropIfExists('users');
        Schema::dropIfExists('consumers');
        Schema::dropIfExists('team_roles');
        Schema::dropIfExists('account_roles');
        Schema::dropIfExists('departments');
    }
};