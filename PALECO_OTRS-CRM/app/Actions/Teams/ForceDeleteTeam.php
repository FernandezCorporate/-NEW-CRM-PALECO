<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAssignment;

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

        // Force delete only undoes a team created by mistake; one that ever handled a ticket must stay for the history
        $hasHistory = Ticket::withTrashed()->where('team_id', $team->id)->exists()
            || TicketAssignment::where('team_id', $team->id)->exists();

        if ($hasHistory) {
            return [
                'success' => false,
                'message' => "Cannot permanently delete {$team->team_name} because it has been assigned to service tickets. Keep it archived instead.",
            ];
        }

        $team->forceDelete();

        return [
            'success' => true,
            'message' => 'Team permanently deleted successfully.',
        ];
    }
}
