<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Transforms the TicketAssignment model into a chronological assignment record for ticket history.
 */
class TicketAssignmentResource extends JsonResource
{
    /*
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'team_name' => $this->whenLoaded('team', fn () => $this->team?->team_name),
            'assigned_by' => $this->whenLoaded('assigner', fn () => $this->assigner?->full_name),
            'assigned_by_role' => $this->whenLoaded('assigner', fn () => $this->assigner?->role?->role_name),
            'assignment_reason' => $this->reason,
            'assigned_at' => $this->created_at?->format('M d, Y h:i A'),
            'unassigned_at' => $this->unassigned_at?->format('M d, Y h:i A'),
        ];
    }
}
