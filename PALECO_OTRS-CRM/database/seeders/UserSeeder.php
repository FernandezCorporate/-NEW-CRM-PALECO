<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/*
 * Populates the system with initial test accounts for local development.
 * Creates one user for each core system role and establishes department/team memberships.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin Account (Web Portal)
        User::create([
            'username' => 'allenglenn',
            'first_name' => 'allen glenn',
            'last_name' => 'fernandez',
            'contact' => '09123456789',
            'role_id' => 1,
            'password' => 'password',
        ]);

        // 2. CWD Officer Account (Web Portal)
        User::create([
            'username' => 'alliah',
            'first_name' => 'alliah',
            'last_name' => 'officer',
            'contact' => '09123456789',
            'role_id' => 2,
            'password' => 'password',
        ]);

        // 3. Supervisor Account (Mobile App Target)
        User::create([
            'username' => 'mycka',
            'first_name' => 'mycka',
            'last_name' => 'supervisor',
            'contact' => '09123456789',
            'role_id' => 3,
            'department_id' => 1,
            'password' => 'password',
        ]);

        // 4. Field Personnel Account (Mobile App Target)
        $personnel = User::create([
            'username' => 'ralph',
            'first_name' => 'ralph',
            'last_name' => 'personnel',
            'contact' => '09123456789',
            'role_id' => 4,
            'password' => 'password',
        ]);

        $firstTeam = Team::first();
        if ($firstTeam) {
            $personnel->teams()->attach($firstTeam->id, ['team_role_id' => 1]);
        }
    }
}
