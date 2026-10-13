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
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('is_force_closed')->default(false)->after('status');
            $table->text('closure_reason')->nullable()->after('is_force_closed');
            $table->foreignUlid('closed_by_id')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['closed_by_id']);
            $table->dropColumn(['is_force_closed', 'closure_reason', 'closed_by_id']);
        });
    }
};

