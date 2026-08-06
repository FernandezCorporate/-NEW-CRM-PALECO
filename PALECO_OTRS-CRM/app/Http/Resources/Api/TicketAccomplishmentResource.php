<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketAccomplishmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'remarks' => $this->remarks,
            'consumer_name' => $this->consumer_name,
            'status' => $this->status->value ?? $this->status, 
            'rejection_reason' => $this->rejection_reason,
            'accomplished_at' => $this->accomplished_at?->toIso8601String(),
            
            'worker' => $this->whenLoaded('accomplishedBy', function () {
                return [
                    'id' => $this->accomplishedBy->id,
                    'name' => $this->accomplishedBy->full_name,
                ];
            }),

            'rejected_by' => $this->whenLoaded('rejectedBy', function () {
                return [
                    'id' => $this->rejectedBy->id,
                    'name' => $this->rejectedBy->full_name,
                ];
            }),
        ];
    }
}