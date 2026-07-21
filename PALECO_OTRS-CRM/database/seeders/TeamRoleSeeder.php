<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TeamRole;

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

        foreach ($roles as $role) {
            TeamRole::create($role);
        }
    }
}