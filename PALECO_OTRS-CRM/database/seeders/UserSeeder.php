<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'username' => 'allenglenn',
            'first_name' => 'allen glenn',
            'middle_name' => 'flojemon',
            'last_name' => 'fernandez',
            'email' => 'allenglennfernandez04@gmail.com',
            'contact' => '09694547493',
            'role' => 'admin',
            'password' => 'password',
        ]);
        
        User::create([
            'username' => 'adrian',
            'first_name' => 'adrian',
            'middle_name' => 'flojemon',
            'last_name' => 'fernandez',
            'email' => 'adrianfernandez04@gmail.com',
            'contact' => '09694547493',
            'role' => 'cwd_officer',
            'password' => 'password',
        ]);
    }
}
