<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/*
 * Master seeder class that prepares a fresh system for first use.
 * Seeds only the fixed reference roles and exactly one administrator account; all other records are created in the admin portal.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TeamRoleSeeder::class,
            AdminAccountSeeder::class,
        ]);
    }
}
