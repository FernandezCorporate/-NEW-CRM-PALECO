<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_accomplishments', function (Blueprint $table) {
            $table->char('approved_by_id', 26)
                  ->nullable()
                  ->after('status'); 
        });
    }

    public function down(): void
    {
        Schema::table('ticket_accomplishments', function (Blueprint $table) {
            $table->dropColumn('approved_by_id');
        });
    }
};