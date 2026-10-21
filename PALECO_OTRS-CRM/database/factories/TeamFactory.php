<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'team_name' => fake()->unique()->words(2, true).' Crew',
            'team_desc' => fake()->sentence(),
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
            'department_id' => Department::first()?->id ?? Department::factory(),
        ];
    }
}
