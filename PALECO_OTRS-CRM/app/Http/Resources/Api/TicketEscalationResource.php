<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketEscalationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'suggested_dept'        => $this->whenLoaded('suggestedDepartment', fn() => $this->suggestedDepartment?->dept_name),
            
            'created_by'            => $this->whenLoaded('creator', fn() => $this->creator?->full_name),
            'creator_role'          => $this->whenLoaded('creator', fn() => $this->creator?->role?->role_name),
            
            'escalation_reason'     => $this->reason,
            
            // Assuming you want formatted timestamps here too, based on the other resource
            'time_requested'        => $this->created_at?->format('M d, Y h:i A'), 
            
            'request_status'        => $this->status,
            'pre_escalation_status' => $this->pre_escalation_status,
            
            // Correct placement of the nullsafe operator on the reviewer relationship
            'reviewed_by'           => $this->whenLoaded('reviewer', fn() => $this->reviewer?->full_name),
            
            'rejection_reason'      => $this->rejection_reason,
            'verified_at'           => $this->reviewed_at?->format('M d, Y h:i A'),
        ];
    }
}
