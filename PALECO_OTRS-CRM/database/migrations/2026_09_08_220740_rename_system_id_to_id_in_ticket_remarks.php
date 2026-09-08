<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_remarks', function (Blueprint $table) {
            $table->renameColumn('system_id', 'id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_remarks', function (Blueprint $table) {
            $table->renameColumn('id', 'system_id');
        });
    }
};