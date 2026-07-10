<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TeamRole;

class TeamRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TeamRole::create([
            'role_name' => 'Team_leader',
            'slug_identifier' => 'team_leader'
        ]);

        TeamRole::create([
            'role_name' => 'Team_member',
            'slug_identifier' => 'team_member'
        ]);

        TeamRole::create([
            'role_name' => 'Backup',
            'slug_identifier' => 'backup'
        ]);
    }
}
