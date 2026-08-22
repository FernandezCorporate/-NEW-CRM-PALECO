<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_escalations', function (Blueprint $table) {
            // 1. Add created_by as strictly required (since you are wiping test data first)
            $table->char('created_by', 26)->after('ticket_id');
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            
            // 2. Drop the foreign key constraint from suggested_department_id
            // This leaves it as a standard unsignedBigInteger column without the relational mapping
            $table->dropForeign(['suggested_department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_escalations', function (Blueprint $table) {
            // 1. Re-apply the foreign key constraint if rolled back
            $table->foreign('suggested_department_id')->references('id')->on('departments')->nullOnDelete();
            
            // 2. Safely drop the created_by constraint and column
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};