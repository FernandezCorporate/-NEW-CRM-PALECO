<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Introduces the idempotency cache table and schema extensions for offline-asynchronous sync.
     */
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key', 64);
            $table->string('endpoint_path', 255);
            $table->string('request_hash', 64);
            $table->string('status', 30)->default('in_progress'); // in_progress, completed, failed
            $table->unsignedInteger('response_code')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key'], 'uniq_user_idempotency_key');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('is_offline_synced')->default(false)->after('status');
            $table->timestamp('synced_at')->nullable()->after('is_offline_synced');
            $table->timestamp('client_started_at')->nullable()->after('started_at');
        });

        Schema::table('ticket_accomplishments', function (Blueprint $table) {
            $table->boolean('is_offline_synced')->default(false)->after('status');
            $table->timestamp('synced_at')->nullable()->after('is_offline_synced');
            $table->timestamp('client_accomplished_at')->nullable()->after('accomplished_at');
            $table->string('idempotency_key', 64)->nullable()->after('synced_at');

            $table->index('idempotency_key', 'idx_accomplishment_idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_accomplishments', function (Blueprint $table) {
            $table->dropIndex('idx_accomplishment_idempotency_key');
            $table->dropColumn(['is_offline_synced', 'synced_at', 'client_accomplished_at', 'idempotency_key']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['is_offline_synced', 'synced_at', 'client_started_at']);
        });

        Schema::dropIfExists('idempotency_records');
    }
};

