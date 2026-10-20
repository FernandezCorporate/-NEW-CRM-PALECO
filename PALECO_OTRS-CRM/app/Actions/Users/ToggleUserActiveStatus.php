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
                        ->whereIn('status', [
                            TicketStatus::ASSIGNED,
                            TicketStatus::IN_PROGRESS,
                            TicketStatus::RESOLVED,
                            TicketStatus::PENDING_ENDORSEMENT,
                        ])
                        ->exists();

                    if ($hasActiveTickets) {
                        throw ValidationException::withMessages([
                            'user' => 'Cannot deactivate user while their assigned crew has tickets that are assigned, in progress, awaiting report verification, or pending endorsement. Please reassign the active tickets or remove the user from the active crew first.',
                        ]);
                    }
                }

                if ($user->role->slug_identifier === 'supervisor' && $user->department_id) {
                    // Locks the department's active supervisors so two deactivations can't both pass this check
                    $supervisorIds = User::query()
                        ->where('department_id', $user->department_id)
                        ->where('is_active', true)
                        ->whereHas('role', fn ($q) => $q->where('slug_identifier', 'supervisor'))
                        ->lockForUpdate()
                        ->pluck('id');

                    $isLastSupervisor = $supervisorIds->reject(fn ($id) => $id === $user->id)->isEmpty();

                    if ($isLastSupervisor) {
                        $openTicketCount = Ticket::query()
                            ->where('department_id', $user->department_id)
                            ->whereIn('status', [
                                TicketStatus::OPEN,
                                TicketStatus::ASSIGNED,
                                TicketStatus::IN_PROGRESS,
                                TicketStatus::PENDING_ENDORSEMENT,
                                TicketStatus::RESOLVED,
                            ])
                            ->count();

                        if ($openTicketCount > 0) {
                            throw ValidationException::withMessages([
                                'user' => "Cannot deactivate the last active supervisor of this department while it has {$openTicketCount} unfinished ticket(s). Assign another supervisor to the department first.",
                            ]);
                        }
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

