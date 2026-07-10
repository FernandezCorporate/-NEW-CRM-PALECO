<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- ADD THIS

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name');
            $table->string('slug_identifier')->unique();
            $table->timestamps();
        });

        // <-- ADD THIS: Wipe the existing pivot data so the foreign key doesn't crash
        DB::table('team_members')->truncate(); 

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn('team_role');
            
            $table->foreignId('team_role_id')
                  ->after('team_id')
                  ->constrained('team_roles');
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropForeign(['team_role_id']);
            $table->dropColumn('team_role_id');
            $table->string('team_role')->after('team_id');
        });

        Schema::dropIfExists('team_roles');
    }
};