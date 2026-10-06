<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Transforms the TicketRemark model into a timeline comment payload with author attribution.
 */
class TicketRemarkResource extends JsonResource
{
    /*
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at_value' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'created_at_display' => $this->created_at ? $this->created_at->format('M d, Y h:i A') : null,

            'author' => $this->whenLoaded('author', function () {
                return [
                    'id' => $this->author->id,
                    'full_name' => $this->author->full_name,
                    'role' => $this->author->role->role_name ?? 'Unknown Role',
                ];
            }),
        ];
    }
}
