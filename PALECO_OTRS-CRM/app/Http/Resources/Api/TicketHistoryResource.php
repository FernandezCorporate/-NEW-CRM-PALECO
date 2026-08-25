<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'assignments' => TicketAssignmentResource::collection($this->whenLoaded('assignments')),
            'escalations' => TicketEscalationResource::collection($this->whenLoaded('escalations')),
        ];
    }
}