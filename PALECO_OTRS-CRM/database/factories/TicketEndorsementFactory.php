<?php

namespace Database\Factories;

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketEndorsement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketEndorsement>
 */
class TicketEndorsementFactory extends Factory
{
    protected $model = TicketEndorsement::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::first()?->id ?? Ticket::factory()->inProgress(),
            'created_by_id' => User::where('role_id', 4)->first()?->id ?? User::factory()->fieldPersonnel(),
            'suggested_department_id' => Department::skip(1)->first()?->id ?? Department::factory(),
            'old_department_id' => fn (array $attributes) => Ticket::find($attributes['ticket_id'])?->department_id,
            'reason' => fake()->sentence(),
            'status' => EndorsementStatus::PENDING,
            'pre_endorsement_status' => TicketStatus::ASSIGNED,
        ];
    }

    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn () => [
            'status' => EndorsementStatus::APPROVED,
            'new_department_id' => fn (array $attributes) => $attributes['suggested_department_id'],
            'reviewed_by_id' => $reviewer?->id ?? User::where('role_id', 2)->first()?->id ?? User::factory()->cwdOfficer(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Not applicable to target department', ?User $reviewer = null): static
    {
        return $this->state(fn () => [
            'status' => EndorsementStatus::REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by_id' => $reviewer?->id ?? User::where('role_id', 2)->first()?->id ?? User::factory()->cwdOfficer(),
            'reviewed_at' => now(),
        ]);
    }
}
