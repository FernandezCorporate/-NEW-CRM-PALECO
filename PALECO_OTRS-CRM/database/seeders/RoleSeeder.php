<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AccountRole;

/*
 * Populates the system's foundational account roles.
 * Defines the primary authorization gates across the web and mobile portals.
 */
class RoleSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $roles = [
            ['role_name' => 'Admin', 'slug_identifier' => 'admin'],
            ['role_name' => 'CWD Officer', 'slug_identifier' => 'cwd_officer'],
            ['role_name' => 'Foreman', 'slug_identifier' => 'foreman'],
            ['role_name' => 'Field Personnel', 'slug_identifier' => 'field_personnel'],
        ];

        foreach ($roles as $role) {
            AccountRole::create($role);
        }
    }
}