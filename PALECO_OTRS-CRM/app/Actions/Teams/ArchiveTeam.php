<?php

namespace App\Actions\Teams;

use App\Enums\TicketStatus;
use App\Events\TeamUpdated;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Single-Action: Soft deletes (archives) an operational team if none of its tickets still depend on its crew.
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

            // Resolved and Pending Endorsement tickets return to this team if the report or endorsement is rejected
            $hasActiveTickets = $lockedTeam->ticket()
                ->whereIn('status', [
                    TicketStatus::ASSIGNED,
                    TicketStatus::IN_PROGRESS,
                    TicketStatus::RESOLVED,
                    TicketStatus::PENDING_ENDORSEMENT,
                ])
                ->exists();

            if ($hasActiveTickets) {
                return [
                    'success' => false,
                    'message' => 'Cannot archive team. It still has tickets that are assigned, in progress, awaiting report verification, or pending endorsement.',
                ];
            }

            $lockedTeam->delete();

            // Members stay attached to an archived team, so they are told on their own channel
            $memberIds = $lockedTeam->members()->pluck('users.id')->all();
            DB::afterCommit(function () use ($lockedTeam, $memberIds) {
                rescue(fn () => TeamUpdated::dispatch($lockedTeam, 'archived', notifiedMemberIds: $memberIds), report: true);
            });

            return [
                'success' => true,
                'message' => 'Team archived successfully.',
                'team' => $lockedTeam,
            ];
        });
    }
}
