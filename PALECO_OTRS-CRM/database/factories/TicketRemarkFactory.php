<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketRemark>
 */
class TicketRemarkFactory extends Factory
{
    protected $model = TicketRemark::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::first()?->id ?? Ticket::factory(),
            'user_id' => User::first()?->id ?? User::factory(),
            'body' => fake()->sentence(),
            'is_internal' => false,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn () => ['is_internal' => true]);
    }
}

