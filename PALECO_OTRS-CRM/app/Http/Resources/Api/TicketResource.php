<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Transforms the Ticket model into a standardized JSON payload.
 * Limits the exposed data to essential fields required by the mobile application list views.
 */
class TicketResource extends JsonResource
{
    /*
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket_number'       => $this->ticket_number,
            'complaint_source'    => $this->complaint_source?->value ?? $this->complaint_source,
            'category_name'       => $this->other_category 
                                        ? $this->other_category_name 
                                        : $this->category?->category_name,
            'purok'               => $this->purok,
            'street'              => $this->street,
            'barangay'            => $this->barangay,
            'status'              => $this->status?->value ?? $this->status,
            'child_tickets_count' => $this->child_tickets_count ?? 0,
        ];
    }
}