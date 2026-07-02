<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique(['dept_name']); 
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Re-add the unique constraint if you rollback
            $table->unique('dept_name');
            
        });
    }
};

