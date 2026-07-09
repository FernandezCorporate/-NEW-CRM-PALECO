<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Use foreignId() instead of id()
            $table->foreignId('role_id')
                  ->nullable()
                  ->after('contact')
                  ->constrained('roles')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // It's best practice to drop the foreign key constraint before the column
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};