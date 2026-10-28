<?php

namespace Database\Seeders;

use App\Models\TeamRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/*
 * Populates the operational roles used specifically within Team assignments.
 */
class TeamRoleSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $roles = [
            ['role_name' => 'Team Leader', 'slug_identifier' => 'team_leader'],
            ['role_name' => 'Team Member', 'slug_identifier' => 'team_member'],
            ['role_name' => 'Backup', 'slug_identifier' => 'backup'],
        ];

        // firstOrCreate lets seeding run again without failing on the unique slug
        foreach ($roles as $role) {
            TeamRole::firstOrCreate(['slug_identifier' => $role['slug_identifier']], $role);
        }
    }
}
