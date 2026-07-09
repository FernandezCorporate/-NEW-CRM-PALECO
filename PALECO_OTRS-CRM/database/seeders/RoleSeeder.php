<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AccountRole;

class RoleSeeder extends Seeder
{
    use WithoutModelEvents;
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AccountRole::create([
            'role_name' => 'admin',
            'role_desc' => 'System management.',
            'slug_identifier' => 'admin'
        ]);

        AccountRole::create([
            'role_name' => 'cwd_officer',
            'role_desc' => 'Ticket creation',
            'slug_identifier' => 'cwd_officer'
        ]);

        AccountRole::create([
            'role_name' => 'foreman',
            'role_desc' => 'Ticket dispatch',
            'slug_identifier' => 'foreman'
        ]);

        AccountRole::create([
            'role_name' => 'field_personnel',
            'role_desc' => 'Ticket progress',
            'slug_identifier' => 'field_personnel'
        ]);
    }
}
