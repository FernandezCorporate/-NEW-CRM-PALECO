<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Drop the old string column
            $table->dropColumn('consumer_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            // Re-add as a strictly typed foreign ULID
            $table->foreignUlid('consumer_id')
                  ->nullable()
                  ->after('parent_ticket_id')
                  ->constrained('consumers', 'id')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['consumer_id']);
            $table->dropColumn('consumer_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            // Revert back to the original string setup if rolled back
            $table->string('consumer_id')->nullable()->after('parent_ticket_id');
        });
    }
};