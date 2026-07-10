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
        Schema::table('ticket_categories', function (Blueprint $Blueprint) {
            // Drops the unique constraint
            $Blueprint->dropUnique('ticket_categories_category_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_categories', function (Blueprint $Blueprint) {
            // Restores the unique constraint if rolled back
            $Blueprint->unique('category_name');
        });
    }
};
