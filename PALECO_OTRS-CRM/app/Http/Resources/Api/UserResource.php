<?php

namespace App\Http\Resources\Api;

use App\Models\TeamRole;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Transforms the User model into a standardized JSON payload.
 * Ensures consistent user profile data formatting across all API endpoints (Auth and Profile).
 */
class UserResource extends JsonResource
{
    /*
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $primaryTeam = $this->relationLoaded('teams') ? $this->teams->first() : null;
        $primaryDept = $this->department ?? $primaryTeam?->department;

        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->full_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name_ext' => $this->full_name,
            'avatar' => $this->avatar_initials,
            'name_ext' => $this->name_ext,
            'email' => $this->email,
            'contact' => $this->contact,
            'role_slug' => $this->role?->slug_identifier,
            'role_name' => $this->role?->role_name,
            'department_id' => $this->department_id ?? $primaryDept?->id,
            'department' => $this->department?->dept_name ?? $primaryDept?->dept_name,
            'teams' => $this->whenLoaded('teams', function () {
                $teamRoles = TeamRole::pluck('role_name', 'id')->toArray();

                return $this->teams->map(function ($team) use ($teamRoles) {
                    $roleId = $team->pivot?->team_role_id;

                    return [
                        'id' => $team->id,
                        'team_name' => $team->team_name,
                        'team_desc' => $team->team_desc,
                        'department_id' => $team->department_id,
                        'department' => $team->department?->dept_name,
                        'shift_start' => $team->shift_start instanceof DateTimeInterface
                            ? $team->shift_start->format('H:i')
                            : ($team->shift_start ? substr((string) $team->shift_start, 0, 5) : null),
                        'shift_end' => $team->shift_end instanceof DateTimeInterface
                            ? $team->shift_end->format('H:i')
                            : ($team->shift_end ? substr((string) $team->shift_end, 0, 5) : null),
                        'team_role_id' => $roleId ? (int) $roleId : null,
                        'role_name' => $roleId ? ($teamRoles[$roleId] ?? null) : null,
                    ];
                });
            }),
        ];
    }
}
