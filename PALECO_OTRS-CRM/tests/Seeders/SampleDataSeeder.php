<?php

namespace Tests\Seeders;

use App\Models\Department;
use App\Models\Team;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TeamRoleSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/*
 * Test-only sample data: departments, teams, ticket categories and one account per role.
 * Lives under tests/ so it is never part of setting up the real system (DatabaseSeeder seeds only roles and one admin).
 */
class SampleDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TeamRoleSeeder::class,
        ]);

        $this->seedDepartments();
        $this->seedTeams();
        $this->seedTicketCategories();
        $this->seedUsers();
    }

    private function seedDepartments(): void
    {
        $departments = [
            [
                'dept_name' => 'Technical Services Department (TSD)',
                'dept_desc' => 'Oversees technical planning, electrical engineering designs, substations, and major power facility operations.',
            ],
            [
                'dept_name' => 'Consumer Welfare Department (CWD)',
                'dept_desc' => 'Handles public intake, customer grievances, billing inquiries, membership registration, and service applications.',
            ],
            [
                'dept_name' => 'Area Operations Department (AOD)',
                'dept_desc' => 'Coordinates local municipal substations, regional field offices, dispatching, and localized response units.',
            ],
            [
                'dept_name' => 'Line Construction & Maintenance Department (LCMD)',
                'dept_desc' => 'Responsible for distribution line maintenance, emergency restorations, pole installations, and vegetation clearing.',
            ],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['dept_name' => $dept['dept_name']], $dept);
        }
    }

    private function seedTeams(): void
    {
        $tsd = Department::where('dept_name', 'like', '%Technical Services%')->first();
        $lcmd = Department::where('dept_name', 'like', '%Line Construction%')->first();
        $aod = Department::where('dept_name', 'like', '%Area Operations%')->first();

        $defaultDeptId = $tsd?->id ?? 1;

        $teams = [
            [
                'team_name' => 'Alpha Emergency Response Crew',
                'team_desc' => 'Primary rapid deployment unit for power outages, transformer trips, and downed conductors.',
                'shift_start' => '08:00:00',
                'shift_end' => '17:00:00',
                'department_id' => $tsd?->id ?? $defaultDeptId,
            ],
            [
                'team_name' => 'Bravo Maintenance Crew',
                'team_desc' => 'Specialized team handling routine pole maintenance, transformer tapping, and preventative servicing.',
                'shift_start' => '08:00:00',
                'shift_end' => '17:00:00',
                'department_id' => $lcmd?->id ?? $defaultDeptId,
            ],
            [
                'team_name' => 'Charlie Heavy Construction Unit',
                'team_desc' => 'Hardware installation, high-voltage line stringing, and new feeder line construction.',
                'shift_start' => '07:00:00',
                'shift_end' => '16:00:00',
                'department_id' => $lcmd?->id ?? $defaultDeptId,
            ],
            [
                'team_name' => 'Delta Night Dispatch Crew',
                'team_desc' => 'Graveyard shift emergency standby team responding to midnight outages and hazards.',
                'shift_start' => '17:00:00',
                'shift_end' => '01:00:00',
                'department_id' => $aod?->id ?? $defaultDeptId,
            ],
        ];

        foreach ($teams as $team) {
            Team::firstOrCreate(['team_name' => $team['team_name']], $team);
        }
    }

    private function seedTicketCategories(): void
    {
        $categories = [
            [
                'category_name' => 'Power Interruption / Blackout',
                'category_desc' => 'Unscheduled complete power loss, localized feeder trip, or neighborhood-wide outage.',
            ],
            [
                'category_name' => 'Fluctuating / Low Voltage',
                'category_desc' => 'Abnormal voltage levels causing flickering lights, appliance underperformance, or brownouts.',
            ],
            [
                'category_name' => 'Defective / Damaged Meter',
                'category_desc' => 'Broken glass, stopped disc/digital readout, burnt meter base, or suspected calibration drift.',
            ],
            [
                'category_name' => 'Sparking / Burning Equipment',
                'category_desc' => 'Urgent electrical hazard involving smoking transformers, arcing drop wires, or sparking fuse cutouts.',
            ],
            [
                'category_name' => 'Leaning / Rotten Electric Post',
                'category_desc' => 'Physical infrastructure hazard: fractured concrete pole, rotting wood post, or vehicular collision damage.',
            ],
            [
                'category_name' => 'Line Clearing / Tree Trimming',
                'category_desc' => 'Foliage or tree branches contacting or endangering primary distribution lines.',
            ],
            [
                'category_name' => 'Reconnection Request',
                'category_desc' => 'Consumer reconnection following settled billing arrears or temporary disconnection.',
            ],
            [
                'category_name' => 'Billing & Account Dispute',
                'category_desc' => 'Inquiries regarding unexpected consumption spikes, uncredited payments, or tariff calculation discrepancies.',
            ],
        ];

        foreach ($categories as $cat) {
            TicketCategory::firstOrCreate(['category_name' => $cat['category_name']], $cat);
        }
    }

    private function seedUsers(): void
    {
        // 1. Admin Account (Web Portal)
        User::create([
            'username' => 'allenglenn',
            'first_name' => 'allen glenn',
            'last_name' => 'fernandez',
            'contact' => '09123456789',
            'role_id' => 1,
            'password' => 'Password',
        ]);

        // 2. CWD Officer Account (Web Portal)
        User::create([
            'username' => 'alliah',
            'first_name' => 'alliah',
            'last_name' => 'officer',
            'contact' => '09123456789',
            'role_id' => 2,
            'password' => 'Password',
        ]);

        // 3. Supervisor Account (Mobile App Target)
        User::create([
            'username' => 'mycka',
            'first_name' => 'mycka',
            'last_name' => 'supervisor',
            'contact' => '09123456789',
            'role_id' => 3,
            'department_id' => 1,
            'password' => 'Password',
        ]);

        // 4. Field Personnel Account (Mobile App Target)
        $personnel = User::create([
            'username' => 'ralph',
            'first_name' => 'ralph',
            'last_name' => 'personnel',
            'contact' => '09123456789',
            'role_id' => 4,
            'password' => 'Password',
        ]);

        $firstTeam = Team::first();
        if ($firstTeam) {
            $personnel->teams()->attach($firstTeam->id, ['team_role_id' => 1]);
        }
    }
}
