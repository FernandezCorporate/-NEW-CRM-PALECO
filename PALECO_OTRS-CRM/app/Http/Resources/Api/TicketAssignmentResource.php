<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Safely check if team loaded, then safely grab the name
            'team_name'          => $this->whenLoaded('team', fn() => $this->team?->team_name),
            
            // Safely check if assigner loaded, then safely grab the full_name
            'assigned_by'        => $this->whenLoaded('assigner', fn() => $this->assigner?->full_name),
            
            // Deep relationship chaining requires multiple nullsafe checks
            'assigned_by_role'   => $this->whenLoaded('assigner', fn() => $this->assigner?->role?->role_name),
            
            'assignment_reason'  => $this->reason,
            'assigned_at'        => $this->created_at?->format('M d, Y h:i A'),
            'unassigned_at'      => $this->unassigned_at?->format('M d, Y h:i A'),
        ];
    }
}
