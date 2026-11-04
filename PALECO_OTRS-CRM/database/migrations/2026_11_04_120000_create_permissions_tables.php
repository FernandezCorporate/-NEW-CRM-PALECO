<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds per-user permissions. Every existing account receives the default permissions
     * of its role, so nobody's access changes when this migration runs.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('module');
            $table->timestamps();
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['user_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            // When the account's permissions last changed; offline work assigned before that moment is still accepted at sync
            $table->timestamp('permissions_updated_at')->nullable();
        });

        $now = now();
        $permissions = config('permissions');

        DB::table('permissions')->insert(collect($permissions)->map(fn (array $meta, string $name) => [
            'name' => $name,
            'label' => $meta['label'],
            'module' => $meta['module'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());

        $permissionIds = DB::table('permissions')->pluck('id', 'name');
        $roles = DB::table('account_roles')->pluck('slug_identifier', 'id');

        DB::table('users')->select('id', 'role_id')->orderBy('id')->chunk(200, function ($users) use ($permissions, $permissionIds, $roles) {
            $rows = [];

            foreach ($users as $user) {
                $slug = $roles[$user->role_id] ?? null;

                foreach ($permissions as $name => $meta) {
                    if ($slug && in_array($slug, $meta['roles'], true)) {
                        $rows[] = ['user_id' => $user->id, 'permission_id' => $permissionIds[$name]];
                    }
                }
            }

            if ($rows) {
                DB::table('permission_user')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions_updated_at');
        });

        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permissions');
    }
};
