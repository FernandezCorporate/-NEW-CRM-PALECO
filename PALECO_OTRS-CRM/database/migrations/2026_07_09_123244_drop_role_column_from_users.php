<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the old string column
            $table->dropColumn('role');
            
            // Optional: If you want to force all users to have a role, 
            // you can make role_id non-nullable here.
            // $table->foreignId('role_id')->nullable(false)->change(); 
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable();
        });
    }
};