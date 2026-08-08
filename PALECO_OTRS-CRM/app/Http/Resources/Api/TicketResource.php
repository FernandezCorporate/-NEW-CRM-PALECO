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
            'id'                  => $this->system_id,
            'ticket_number'       => $this->ticket_number,
            'complaint_source'    => $this->complaint_source?->value ?? $this->complaint_source,

            'ticket_subject'      => $this->subject,
            'complaint_description' => $this->complaint_description,
            'category_name'       => $this->other_category 
                                        ? $this->other_category_name 
                                        : $this->category?->category_name,
            'purok'               => $this->purok,
            'street'              => $this->street,
            'barangay'            => $this->barangay,
            'landmark'            => $this->landmark,

            'team_id'             => $this->team_id,
            'team_name'           => $this->team?->team_name,
            'created_by'          => $this->created_by,
            'created_by_name'     => $this->creator?->full_name,

            'reported_at'         => $this->reported_at?->toDateTimeString(),
            'started_at'          => $this->started_at?->toDateTimeString(),
            'resolved_at'         => $this->resolved_at?->toDateTimeString(),
            'closed_at'           => $this->closed_at?->toDateTimeString(),
            
            'created_at'          => $this->created_at?->toDateTimeString(),
            'updated_at'          => $this->updated_at?->toDateTimeString(),

            'status'              => $this->status?->value ?? $this->status,
            'child_tickets_count' => $this->child_tickets_count ?? 0,
        ];
    }
}