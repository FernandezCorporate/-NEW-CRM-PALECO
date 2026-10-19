<?php

namespace App\Actions\Teams;

use App\Enums\TicketStatus;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Single-Action: Soft deletes (archives) an operational team if no active service tickets are assigned.
 */
class ArchiveTeam
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
        return DB::transaction(function () use ($team) {
            $lockedTeam = Team::where('id', $team->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedTeam || $lockedTeam->trashed()) {
                return [
                    'success' => false,
                    'message' => 'Conflict: This team has already been archived by an administrator.',
                ];
            }

            $hasActiveTickets = $lockedTeam->ticket()
                ->whereIn('status', [
                    TicketStatus::ASSIGNED,
                    TicketStatus::IN_PROGRESS,
                ])
                ->exists();

            if ($hasActiveTickets) {
                return [
                    'success' => false,
                    'message' => 'Cannot archive team. They currently have active assigned service tickets.',
                ];
            }

            $lockedTeam->delete();

            return [
                'success' => true,
                'message' => 'Team archived successfully.',
                'team' => $lockedTeam,
            ];
        });
    }
}

