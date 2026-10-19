<?php

namespace App\Actions\Teams;

use App\Models\Team;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Single-Action: Updates an existing team and dynamically syncs roster changes using optimistic concurrency control.
 */
class UpdateTeam
{
    public function __construct(
        protected ValidateMemberShiftOverlaps $validateShiftOverlaps
    ) {}

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

            $targetShiftStart = $teamDetails['shift_start'] ?? ($lockedTeam->shift_start instanceof DateTimeInterface ? $lockedTeam->shift_start->format('H:i') : substr((string) $lockedTeam->shift_start, 0, 5));
            $targetShiftEnd = $teamDetails['shift_end'] ?? ($lockedTeam->shift_end instanceof DateTimeInterface ? $lockedTeam->shift_end->format('H:i') : substr((string) $lockedTeam->shift_end, 0, 5));

            $this->validateShiftOverlaps->execute($assignedMembers, $targetShiftStart, $targetShiftEnd, $lockedTeam->id);

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

