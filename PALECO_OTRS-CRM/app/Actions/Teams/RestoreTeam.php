<?php

namespace App\Actions\Teams;

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

        if (Team::where('team_name', $team->team_name)
            ->where('department_id', $team->department_id)
            ->exists()) {
            return [
                'success' => false,
                'message' => 'Cannot restore team. A team with the same name already exists within this department.',
            ];
        }

        $team->restore();

        return [
            'success' => true,
            'message' => 'Team restored successfully.',
            'team' => $team,
        ];
    }
}

