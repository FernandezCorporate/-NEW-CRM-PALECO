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
            'slug_identifier' => 'admin'
        ]);

        AccountRole::create([
            'role_name' => 'cwd_officer',
            'slug_identifier' => 'cwd_officer'
        ]);

        AccountRole::create([
            'role_name' => 'foreman',
            'slug_identifier' => 'foreman'
        ]);

        AccountRole::create([
            'role_name' => 'field_personnel',
            'slug_identifier' => 'field_personnel'
        ]);
    }
}
