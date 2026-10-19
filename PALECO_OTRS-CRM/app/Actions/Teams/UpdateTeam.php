<?php

namespace App\Actions\Teams;

use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single-Action: Updates an existing team and dynamically syncs roster changes using optimistic concurrency control.
 */
class UpdateTeam
{
    /**
     * @return array{success: bool, changed?: bool, message?: string, team?: Team}
     */
    public function handle(Team $team, array $teamDetails, array $assignedMembers): array
    {
        return $this->execute($team, $teamDetails, $assignedMembers);
    }

    /**
     * @return array{success: bool, changed?: bool, message?: string, team?: Team}
     */
    public function execute(Team $team, array $teamDetails, array $assignedMembers): array
    {
        if ($team->trashed()) {
            return [
                'success' => false,
                'message' => 'Conflict: This team has been archived by an administrator and can no longer be modified.',
            ];
        }

        $originalUpdatedAt = $teamDetails['original_updated_at'];
        unset($teamDetails['original_updated_at']);

        return DB::transaction(function () use ($team, $teamDetails, $assignedMembers, $originalUpdatedAt) {
            $lockedTeam = Team::where('id', $team->id)->lockForUpdate()->first();

            // Safe Carbon-based optimistic concurrency timestamp check
            if (! $lockedTeam->updated_at->eq(Carbon::parse($originalUpdatedAt))) {
                return [
                    'success' => false,
                    'message' => 'Conflict: This team was modified by another user while you were editing.',
                ];
            }

            // Compare roster states to detect membership changes
            $oldRoster = $lockedTeam->members()->get()->mapWithKeys(function ($m) {
                return [$m->id => (int) $m->pivot->team_role_id];
            })->toArray();

            $newRoster = collect($assignedMembers)->mapWithKeys(function ($member) {
                return [$member['user_id'] => (int) $member['team_role_id']];
            })->toArray();

            ksort($oldRoster);
            ksort($newRoster);

            $membersChanged = ($oldRoster !== $newRoster);

            $lockedTeam->fill($teamDetails);
            $isTeamDirty = $lockedTeam->isDirty();

            if ($isTeamDirty) {
                $lockedTeam->save();
            } elseif ($membersChanged) {
                $lockedTeam->touch();
            }

            if ($membersChanged) {
                $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                    return [$member['user_id'] => ['team_role_id' => $member['team_role_id']]];
                });

                $lockedTeam->members()->sync($formattedMembers);

                activity()
                    ->useLog('Teams')
                    ->performedOn($lockedTeam)
                    ->event('roster_updated')
                    ->withProperties([
                        'old' => ['member_ids' => array_keys($oldRoster)],
                        'attributes' => ['member_ids' => array_keys($newRoster)],
                    ])
                    ->log("{$lockedTeam->team_name} roster has been modified");
            }

            $freshTeam = $lockedTeam->fresh(['members' => function ($query) {
                $query->select('users.id', 'users.first_name', 'users.middle_name', 'users.last_name', 'users.name_ext');
            }]);

            return [
                'success' => true,
                'changed' => $isTeamDirty || $membersChanged,
                'team' => $freshTeam,
            ];
        });
    }
}

