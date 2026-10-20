<?php

namespace Database\Factories;

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'ticket_number' => 'TCK-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'parent_ticket_id' => null,
            'consumer_id' => null,
            'consumer_contact' => '09'.fake()->numerify('#########'),
            'complaint_source' => ComplaintSources::PHONE_CALL,
            'complaint_description' => fake()->paragraph(),
            'category_id' => TicketCategory::first()?->id ?? TicketCategory::factory(),
            'other_category' => false,
            'other_category_name' => null,
            'purok' => 'Purok '.fake()->numberBetween(1, 10),
            'street' => fake()->streetName(),
            'barangay' => fake()->city(),
            'landmark' => fake()->secondaryAddress(),
            'department_id' => Department::first()?->id ?? Department::factory(),
            'team_id' => null,
            'created_by_id' => User::where('role_id', 2)->first()?->id ?? User::factory()->cwdOfficer(),
            'status' => TicketStatus::OPEN,
            'reported_at' => now(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::OPEN,
            'team_id' => null,
        ]);
    }

    public function assigned(?Team $team = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::ASSIGNED,
            'team_id' => $team?->id ?? Team::first()?->id ?? Team::factory(),
        ]);
    }

    public function inProgress(?Team $team = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::IN_PROGRESS,
            'team_id' => $team?->id ?? Team::first()?->id ?? Team::factory(),
            'started_at' => now(),
        ]);
    }

    public function resolved(?Team $team = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::RESOLVED,
            'team_id' => $team?->id ?? Team::first()?->id ?? Team::factory(),
            'started_at' => now()->subHours(2),
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::CLOSED,
            'closed_at' => now(),
            'closed_by_id' => User::where('role_id', 1)->first()?->id ?? User::factory()->admin(),
        ]);
    }

    public function cancelled(string $reason = 'Invalid or duplicate report'): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::CANCELLED,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);
    }

    public function pendingEndorsement(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::PENDING_ENDORSEMENT,
        ]);
    }

    public function endorsed(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::ENDORSED,
            'is_endorsed' => true,
        ]);
    }
}

