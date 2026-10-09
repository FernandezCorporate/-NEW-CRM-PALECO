<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/*
 * Master seeder class that orchestrates the execution of all other seeders.
 * Ensures required reference tables (roles, departments, categories) are populated before users and teams.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TeamRoleSeeder::class,
            DepartmentSeeder::class,
            TeamSeeder::class,
            TicketCategorySeeder::class,
            UserSeeder::class,
        ]);
    }
}
