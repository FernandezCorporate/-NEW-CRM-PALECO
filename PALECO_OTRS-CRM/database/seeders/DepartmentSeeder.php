<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/*
 * Populates core operational departments for PALECO.
 * Ensures organizational units exist for ticket routing, team hierarchy, and supervisory access.
 */
class DepartmentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
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
}
