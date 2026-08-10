<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketDetailedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->system_id,
            'ticket_number'       => $this->ticket_number,
            'status'              => $this->status?->value ?? $this->status,
            'reported_at'         => $this->reported_at?->toDateTimeString(),
            'started_at'          => $this->started_at?->toDateTimeString(),
            'resolved_at'         => $this->resolved_at?->toDateTimeString(),
            'closed_at'           => $this->closed_at?->toDateTimeString(),
            
            // Eager-loaded Dispatcher/Creator
            'creator'             => $this->whenLoaded('creator', function () {
                return [
                    'id'   => $this->creator->id,
                    'name' => $this->creator->full_name,
                    'role' => $this->creator->role?->role_name ?? 'Dispatcher',
                ];
            }),

            // Grouped Complaint Data
            'complaint'           => [
                'source'        => $this->complaint_source?->value ?? $this->complaint_source,
                'category_name' => $this->other_category 
                                        ? $this->other_category_name 
                                        : $this->category?->category_name,
                'description'   => $this->complaint_description,
                'full_address'  => $this->subject, // Utilizes your existing concatenated accessor
                'landmark'      => $this->landmark,
            ],

            // Eager-loaded Team with Members
            'team'                => $this->when($this->team_id !== null, function () {
                return [
                    'id'          => $this->team->id,
                    'team_name'   => $this->team->team_name,
                    'shift_start' => $this->team->shift_start?->format('H:i'),
                    'shift_end'   => $this->team->shift_end?->format('H:i'),
                    'personnel'   => $this->team->members->map(function ($member) {
                        return [
                            'id'   => $member->id,
                            'name' => $member->full_name,
                        ];
                    })
                ];
            }),

            // Eager-loaded Status Logs for the Timeline
            'status_logs'         => $this->whenLoaded('statusLog', function () {
                return $this->statusLog->map(function ($log) {
                    return [
                        'id'              => $log->id,
                        'old_status'      => $log->old_status?->value ?? $log->old_status,
                        'new_status'      => $log->new_status?->value ?? $log->new_status,
                        'changed_by_name' => $log->updater?->full_name,
                        'created_at'      => $log->created_at?->toDateTimeString(),
                    ];
                });
            }),

            // Eager-loaded Child Tickets
            'child_tickets_count' => $this->child_tickets_count ?? 0,
            'child_tickets'       => $this->whenLoaded('childTickets', function () {
                return $this->childTickets->map(function ($child) {
                    return [
                        'id'            => $child->system_id,
                        'ticket_number' => $child->ticket_number,
                        'status'        => $child->status?->value ?? $child->status,
                    ];
                });
            }),
        ];
    }
}