<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/*
 * Master seeder class that orchestrates the execution of all other seeders.
 * Ensures required reference tables (like roles) are populated before users.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            TeamRoleSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
        ]);
    }
}