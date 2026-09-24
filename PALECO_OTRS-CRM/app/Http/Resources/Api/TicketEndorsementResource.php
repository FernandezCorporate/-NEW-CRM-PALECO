<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketEndorsementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'suggested_dept'         => $this->whenLoaded('suggestedDepartment', fn() => $this->suggestedDepartment?->dept_name),
            
            'created_by'             => $this->whenLoaded('creator', fn() => $this->creator?->full_name),
            'creator_role'           => $this->whenLoaded('creator', fn() => $this->creator?->role?->role_name),
            
            'endorsement_reason'     => $this->reason,
            
            'time_requested'         => $this->created_at?->format('M d, Y h:i A'), 
            
            'request_status'         => $this->status,
            'pre_endorsement_status' => $this->pre_endorsement_status,
            
            'reviewed_by'            => $this->whenLoaded('reviewer', fn() => $this->reviewer?->full_name),
            
            'rejection_reason'       => $this->rejection_reason,
            'verified_at'            => $this->reviewed_at?->format('M d, Y h:i A'),
        ];
    }
}