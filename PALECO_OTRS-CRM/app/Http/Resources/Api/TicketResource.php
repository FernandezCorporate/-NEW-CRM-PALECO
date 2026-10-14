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
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'consumer_contact' => $this->consumer_contact,
            'complaint_source' => $this->complaint_source?->value ?? $this->complaint_source,

            'ticket_subject' => $this->subject,
            'complaint_description' => $this->complaint_description,

            // Department Silo
            'department_id' => $this->department_id,
            'department_name' => $this->department?->trashed()
                ? $this->department->dept_name . ' (Archived)'
                : $this->department?->dept_name,

            // Category Details
            'category_id' => $this->category_id,
            'category_name' => $this->other_category
                ? $this->other_category_name
                : ($this->category?->trashed()
                    ? $this->category->category_name . ' (Archived)'
                    : $this->category?->category_name),
            'is_other_category' => (bool) $this->other_category,
            'other_category_name' => $this->other_category_name,

            // Hierarchy
            'is_child' => ! empty($this->parent_ticket_id),
            'parent_ticket_id' => $this->parent_ticket_id,
            'parent_ticket_number' => $this->parentTicket?->ticket_number,
            'child_tickets_count' => $this->child_tickets_count ?? 0,

            // Location
            'purok' => $this->purok,
            'street' => $this->street,
            'barangay' => $this->barangay,
            'landmark' => $this->landmark,

            // Team & Dispatcher
            'team_id' => $this->team_id,
            'team_name' => $this->team?->trashed()
                ? $this->team->team_name . ' (Archived)'
                : $this->team?->team_name,
            'created_by' => $this->created_by_id,
            'created_by_name' => $this->creator?->full_name,

            // Timestamps
            'reported_at' => $this->reported_at?->format('M d, Y h:i A'),
            'started_at' => $this->started_at?->format('M d, Y h:i A'),
            'resolved_at' => $this->resolved_at?->format('M d, Y h:i A'),
            'closed_at' => $this->closed_at?->format('M d, Y h:i A'),
            'created_at' => $this->created_at?->format('M d, Y h:i A'),
            'updated_at' => $this->updated_at?->format('M d, Y h:i A'),

            // Lifecycle & Exceptions
            'status' => $this->status?->value ?? $this->status,
            'is_force_closed' => (bool) ($this->is_force_closed ?? false),
            'closure_reason' => $this->closure_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->format('M d, Y h:i A'),

            // Offline Sync Telemetry
            'is_offline_synced' => (bool) ($this->is_offline_synced ?? false),
            'synced_at' => $this->synced_at?->format('M d, Y h:i A'),
            'client_started_at' => $this->client_started_at?->format('M d, Y h:i A'),

            'active_assignment' => $this->whenLoaded('activeAssignment', function () {
                if (! $this->activeAssignment) {
                    return null;
                }

                return [
                    'team_id' => $this->activeAssignment->team_id,
                    'team_name' => $this->activeAssignment->team?->team_name,
                    'assigned_by' => $this->activeAssignment->assigner?->full_name,
                    'assigned_by_role' => $this->activeAssignment->assigner?->role?->role_name,
                    'reason' => $this->activeAssignment->reason,
                    'assigned_at' => $this->activeAssignment->created_at?->format('M d, Y h:i A'),
                ];
            }),
        ];
    }
}
