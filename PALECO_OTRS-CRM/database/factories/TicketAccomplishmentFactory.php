<?php

namespace Database\Factories;

use App\Enums\TicketAccomplishmentStatus;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketAccomplishment>
 */
class TicketAccomplishmentFactory extends Factory
{
    protected $model = TicketAccomplishment::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::first()?->id ?? Ticket::factory()->inProgress(),
            'accomplished_by_id' => User::where('role_id', 4)->first()?->id ?? User::factory()->fieldPersonnel(),
            'remarks' => fake()->paragraph(),
            'accomplished_at' => now(),
            'consumer_name' => fake()->name(),
            'signature_path' => 'signatures/test_signature.png',
            'status' => TicketAccomplishmentStatus::PENDING,
            'is_offline_synced' => false,
            'idempotency_key' => fake()->uuid(),
        ];
    }

    public function approved(?User $supervisor = null): static
    {
        return $this->state(fn () => [
            'status' => TicketAccomplishmentStatus::APPROVED,
            'approved_by_id' => $supervisor?->id ?? User::where('role_id', 3)->first()?->id ?? User::factory()->supervisor(),
        ]);
    }

    public function rejected(string $reason = 'Incomplete documentation', ?User $supervisor = null): static
    {
        return $this->state(fn () => [
            'status' => TicketAccomplishmentStatus::REJECTED,
            'rejected_by_id' => $supervisor?->id ?? User::where('role_id', 3)->first()?->id ?? User::factory()->supervisor(),
            'rejection_reason' => $reason,
        ]);
    }
}
