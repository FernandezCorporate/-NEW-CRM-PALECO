<?php

namespace App\Actions\Users;

use App\Enums\TicketStatus;
use App\Events\AccountUpdated;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single-Action: Commits updates to an existing user profile with optimistic concurrency control.
 */
class UpdateUser
{
    /**
     * @return array{success: bool, changed: bool, message?: string}
     */
    public function handle(User $user, array $data): array
    {
        return $this->execute($user, $data);
    }

    /**
     * @return array{success: bool, changed: bool, message?: string}
     */
    public function execute(User $user, array $data): array
    {
        $originalUpdatedAt = $data['original_updated_at'] ?? null;
        unset($data['original_updated_at']);

        if ($user->role && $user->role->slug_identifier === 'field_personnel') {
            $data['department_id'] = null;
        }

        return DB::transaction(function () use ($user, $data, $originalUpdatedAt) {
            $lockedUser = User::query()->where('id', $user->id)->lockForUpdate()->first();

            if ($originalUpdatedAt && ! $lockedUser->updated_at->eq(Carbon::parse($originalUpdatedAt))) {
                return ['success' => false, 'changed' => false, 'message' => 'Conflict: This user profile was modified by another admin while you were editing.'];
            }

            $leavesDepartment = $user->role?->slug_identifier === 'supervisor'
                && $lockedUser->is_active
                && $lockedUser->department_id
                && array_key_exists('department_id', $data)
                && (int) $data['department_id'] !== (int) $lockedUser->department_id;

            if ($leavesDepartment) {
                // Locks the department's active supervisors so two moves can't both pass this check
                $supervisorIds = User::query()
                    ->where('department_id', $lockedUser->department_id)
                    ->where('is_active', true)
                    ->whereHas('role', fn ($q) => $q->where('slug_identifier', 'supervisor'))
                    ->lockForUpdate()
                    ->pluck('id');

                $isLastSupervisor = $supervisorIds->reject(fn ($id) => $id === $lockedUser->id)->isEmpty();

                if ($isLastSupervisor) {
                    $openTicketCount = Ticket::query()
                        ->where('department_id', $lockedUser->department_id)
                        ->whereIn('status', [
                            TicketStatus::OPEN,
                            TicketStatus::ASSIGNED,
                            TicketStatus::IN_PROGRESS,
                            TicketStatus::PENDING_ENDORSEMENT,
                            TicketStatus::RESOLVED,
                        ])
                        ->count();

                    if ($openTicketCount > 0) {
                        return ['success' => false, 'changed' => false, 'message' => "Cannot move the last active supervisor out of this department while it has {$openTicketCount} unfinished ticket(s). Assign another supervisor to the department first."];
                    }
                }
            }

            $lockedUser->fill($data);

            if ($lockedUser->isClean()) {
                return ['success' => true, 'changed' => false];
            }

            $departmentChanged = $lockedUser->isDirty('department_id');
            $previousDepartmentId = $lockedUser->getOriginal('department_id');

            $lockedUser->save();

            // A moved supervisor must leave the old department channel and join the new one
            if ($departmentChanged) {
                DB::afterCommit(function () use ($lockedUser, $previousDepartmentId) {
                    rescue(fn () => AccountUpdated::dispatch($lockedUser, 'department_changed', $previousDepartmentId), report: true);
                });
            }

            return ['success' => true, 'changed' => true];
        });
    }
}
