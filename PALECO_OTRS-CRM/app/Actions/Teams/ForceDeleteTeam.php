<?php

namespace App\Actions\Teams;

use App\Models\Team;

/**
 * Single-Action: Permanently deletes a trashed team ensuring no ticket relationship integrity is compromised.
 */
class ForceDeleteTeam
{
    /**
     * @return array{success: bool, message: string}
     */
    public function handle(Team $team): array
    {
        return $this->execute($team);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(Team $team): array
    {
        if (! $team->trashed()) {
            return [
                'success' => false,
                'message' => 'Cannot permanently delete an active team. Please archive it first.',
            ];
        }

        if ($team->ticket()->exists()) {
            return [
                'success' => false,
                'message' => "Cannot permanently delete {$team->team_name} because it is currently assigned to existing service tickets.",
            ];
        }

        $team->forceDelete();

        return [
            'success' => true,
            'message' => 'Team permanently deleted successfully.',
        ];
    }
}

