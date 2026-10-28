<?php

namespace Database\Seeders;

use App\Models\AccountRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/*
 * Creates the single administrator account needed to set up the system.
 * Every other account, department, team and category is created through the admin portal.
 */
class AdminAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $adminRole = AccountRole::where('slug_identifier', 'admin')->firstOrFail();

        // firstOrCreate keeps the account untouched when seeding runs again, including a password changed since setup
        User::firstOrCreate(
            ['username' => 'PALECO_ADMIN_1'],
            [
                // Placeholder profile details; the admin updates them from the portal after the first sign-in
                'first_name' => 'paleco',
                'last_name' => 'administrator',
                'contact' => '09000000000',
                'role_id' => $adminRole->id,
                'password' => 'OTRS-Testing',
            ]
        );
    }
}
