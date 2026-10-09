<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Team;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/*
 * Populates operational field response teams linked to departments.
 * Defines standard day/night shift schedules for emergency and maintenance work.
 */
class TeamSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
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
}
