<?php

namespace App\Actions\Users;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Toggles the active status of a user and invalidates tokens/sessions/memberships if deactivated.
 */
class ToggleUserActiveStatus
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user, bool $isActive): void
    {
        $this->execute($user, $isActive);
    }

    /**
     * @throws ValidationException
     */
    public function execute(User $user, bool $isActive): void
    {
        DB::transaction(function () use ($user, $isActive) {
            if (! $isActive) {
                if ($user->role->slug_identifier === 'field_personnel') {
                    $hasActiveTickets = Ticket::query()
                        ->whereIn('team_id', $user->teams()->pluck('teams.id'))
                        ->whereIn('status', [TicketStatus::ASSIGNED, TicketStatus::IN_PROGRESS])
                        ->exists();

                    if ($hasActiveTickets) {
                        throw ValidationException::withMessages([
                            'user' => 'Cannot deactivate user while their assigned crew has active tickets assigned or in progress. Please reassign the active tickets or remove the user from the active crew first.',
                        ]);
                    }
                }

                $user->tokens()->delete();

                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }

                if ($user->role->slug_identifier === 'field_personnel') {
                    $user->teams()->detach();
                }
            }

            $user->is_active = $isActive;
            $user->save();
        });
    }
}

