<?php

namespace Database\Factories;

use App\Models\Consumer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consumer>
 */
class ConsumerFactory extends Factory
{
    protected $model = Consumer::class;

    public function definition(): array
    {
        return [
            'acct_no' => fake()->unique()->numerify('####-######'),
            'acct_code' => fake()->unique()->numerify('ACC-#####'),
            'name' => fake()->name(),
            'address' => fake()->address(),
            'status' => 'Active',
            'meter_serial' => fake()->bothify('MTR-#####-???'),
        ];
    }
}

