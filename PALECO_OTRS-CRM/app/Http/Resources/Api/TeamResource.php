<?php

namespace App\Http\Resources\Api;

use App\Models\TeamRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Transforms the Team model into a standardized JSON payload.
 * Provides core team details, active member counts, roster list, and workload statistics.
 */
class TeamResource extends JsonResource
{
    /*
     * Statically cache the team roles to prevent N+1 database queries 
     * when iterating over hundreds of nested team members.
     */
    protected static array $roles = [];

    public function toArray(Request $request): array
    {
        if (empty(self::$roles)) {
            self::$roles = TeamRole::pluck('role_name', 'id')->toArray();
        }

        return [
            'id'              => $this->id,
            'team_name'       => $this->team_name,
            'team_desc'       => $this->team_desc, 
            'shift_start'     => $this->shift_start,
            'shift_end'       => $this->shift_end,

            // --- LIFECYCLE STATE ---
            'deleted_at'      => $this->deleted_at,
            'is_archived'     => !is_null($this->deleted_at),
            
            'updated_at'      => $this->updated_at,
            'members_count'   => $this->members_count ?? 0,
            
            // --- WORKLOAD STATISTICS ---
            'ticket_stats'    => [
                'total'       => $this->tickets_total ?? 0,
                'open'        => $this->tickets_open ?? 0,
                'assigned'    => $this->tickets_assigned ?? 0,
                'in_progress' => $this->tickets_in_progress ?? 0,
                'resolved'    => $this->tickets_resolved ?? 0,
                'closed'      => $this->tickets_closed ?? 0,
            ],
            
            // --- NESTED ROSTER ---
            'members'         => $this->whenLoaded('members', function () {
                return $this->members->map(function ($member) {
                    return [
                        'id'           => $member->id,
                        'full_name'    => $member->full_name,
                        'team_role_id' => $member->pivot->team_role_id ?? null,
                        'role_name'    => self::$roles[$member->pivot->team_role_id] ?? 'Unknown Role',
                    ];
                });
            }),
        ];
    }
}