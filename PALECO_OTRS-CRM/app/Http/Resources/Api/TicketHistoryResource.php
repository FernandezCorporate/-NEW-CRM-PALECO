<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Transforms the combined assignment and endorsement history of a ticket into a unified timeline payload.
 */
class TicketHistoryResource extends JsonResource
{
    /*
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'assignments' => TicketAssignmentResource::collection($this->whenLoaded('assignments')),
            'endorsements' => TicketEndorsementResource::collection($this->whenLoaded('endorsements')),
        ];
    }
}
