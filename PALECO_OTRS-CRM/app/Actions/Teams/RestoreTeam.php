<?php

namespace App\Actions\Teams;

use App\Events\TeamUpdated;
use App\Models\Department;
use App\Models\Team;

/**
 * Single-Action: Restores an archived team if no active team within the department shares the same name.
 */
class RestoreTeam
{
    /**
     * @return array{success: bool, message: string, team?: Team}
     */
    public function handle(Team $team): array
    {
        return $this->execute($team);
    }

    /**
     * @return array{success: bool, message: string, team?: Team}
     */
    public function execute(Team $team): array
    {
        if (! $team->trashed()) {
            return [
                'success' => false,
                'message' => 'This team is already active and cannot be restored.',
            ];
        }

        // A team restored into an archived department would have no active supervisor to manage it
        $department = Department::withTrashed()->find($team->department_id);

        if (! $department || $department->trashed()) {
            return [
                'success' => false,
                'message' => 'Cannot restore team. Its department is archived; restore the department first.',
            ];
        }

        if (Team::where('team_name', $team->team_name)
            ->where('department_id', $team->department_id)
            ->exists()) {
            return [
                'success' => false,
                'message' => 'Cannot restore team. A team with the same name already exists within this department.',
            ];
        }

        $team->restore();

        rescue(fn () => TeamUpdated::dispatch($team, 'restored', notifiedMemberIds: $team->members()->pluck('users.id')->all()), report: true);

        return [
            'success' => true,
            'message' => 'Team restored successfully.',
            'team' => $team,
        ];
    }
}
