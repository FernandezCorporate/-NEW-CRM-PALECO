<?php

namespace App\Actions\Teams;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Validates that none of the proposed members have overlapping shifts in other active teams.
 */
class ValidateMemberShiftOverlaps
{
    /**
     * Checks if two 24-hour time ranges overlap, gracefully handling graveyard / cross-midnight shifts.
     */
    public static function doShiftsOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        $toMinutes = function (string $time): int {
            $parts = explode(':', $time);
            return ((int) $parts[0]) * 60 + ((int) ($parts[1] ?? 0));
        };

        $getIntervals = function (int $start, int $end): array {
            if ($start < $end) {
                return [[$start, $end]];
            }
            // Cross-midnight / graveyard shift: [start, 1440) and [0, end)
            return [[$start, 1440], [0, $end]];
        };

        $minA_start = $toMinutes($startA);
        $minA_end = $toMinutes($endA);
        $minB_start = $toMinutes($startB);
        $minB_end = $toMinutes($endB);

        $intervalsA = $getIntervals($minA_start, $minA_end);
        $intervalsB = $getIntervals($minB_start, $minB_end);

        foreach ($intervalsA as [$aStart, $aEnd]) {
            foreach ($intervalsB as [$bStart, $bEnd]) {
                if (max($aStart, $bStart) < min($aEnd, $bEnd)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @throws ValidationException
     */
    public function handle(array $assignedMembers, string $shiftStart, string $shiftEnd, ?string $ignoreTeamId = null): void
    {
        $this->execute($assignedMembers, $shiftStart, $shiftEnd, $ignoreTeamId);
    }

    /**
     * @throws ValidationException
     */
    public function execute(array $assignedMembers, string $shiftStart, string $shiftEnd, ?string $ignoreTeamId = null): void
    {
        if (empty($assignedMembers)) {
            return;
        }

        $userIds = collect($assignedMembers)->pluck('user_id')->unique()->filter()->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $users = User::with(['teams' => function ($query) use ($ignoreTeamId) {
            $query->whereNull('teams.deleted_at');
            if ($ignoreTeamId) {
                $query->where('teams.id', '!=', $ignoreTeamId);
            }
        }])->whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            foreach ($user->teams as $otherTeam) {
                $otherStart = $otherTeam->shift_start instanceof DateTimeInterface
                    ? $otherTeam->shift_start->format('H:i')
                    : substr((string) $otherTeam->shift_start, 0, 5);
                $otherEnd = $otherTeam->shift_end instanceof DateTimeInterface
                    ? $otherTeam->shift_end->format('H:i')
                    : substr((string) $otherTeam->shift_end, 0, 5);

                if (self::doShiftsOverlap($shiftStart, $shiftEnd, $otherStart, $otherEnd)) {
                    $fullName = trim("{$user->first_name} {$user->last_name}");
                    throw ValidationException::withMessages([
                        'members' => "Member '{$fullName}' is already assigned to '{$otherTeam->team_name}' with an overlapping shift ({$otherStart} - {$otherEnd}).",
                    ]);
                }
            }
        }
    }
}

