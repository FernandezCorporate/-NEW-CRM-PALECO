<?php

namespace App\Actions\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Single-Action: Creates a new operational team and synchronizes initial roster assignments.
 */
class CreateTeam
{
    public function handle(array $teamDetails, array $assignedMembers): Team
    {
        return $this->execute($teamDetails, $assignedMembers);
    }

    public function execute(array $teamDetails, array $assignedMembers): Team
    {
        return DB::transaction(function () use ($teamDetails, $assignedMembers) {
            $team = Team::create($teamDetails);

            if (! empty($assignedMembers)) {
                $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                    return [$member['user_id'] => ['team_role_id' => $member['team_role_id']]];
                });

                $team->members()->sync($formattedMembers);
            }

            return $team->fresh(['members' => function ($query) {
                $query->select('users.id', 'users.first_name', 'users.middle_name', 'users.last_name', 'users.name_ext');
            }]);
        });
    }
}
