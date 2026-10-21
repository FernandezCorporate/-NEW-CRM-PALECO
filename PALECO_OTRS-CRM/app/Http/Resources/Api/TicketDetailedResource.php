<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Transforms the Ticket model into a comprehensive detailed payload for mobile ticket screens.
 * Includes nested complainant data, status transition history, assigned crew, and child tickets.
 */
class TicketDetailedResource extends JsonResource
{
    /*
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'status' => $this->status?->value ?? $this->status,

            // Department Silo
            'department_id' => $this->department_id,
            'department_name' => $this->department?->trashed()
                ? $this->department->dept_name.' (Archived)'
                : $this->department?->dept_name,

            // Hierarchy Navigation
            'is_child' => ! empty($this->parent_ticket_id),
            'is_endorsed' => (bool) $this->is_endorsed,
            'parent_ticket_id' => $this->parent_ticket_id,
            'parent_ticket_number' => $this->parentTicket?->ticket_number,

            // Where a child ticket came from: null for an origin ticket, otherwise endorsement or manual
            'created_via' => $this->when($this->resource->relationLoaded('sourceEndorsement'), fn () => $this->createdVia()),
            'endorsement_origin' => $this->when(
                $this->resource->relationLoaded('sourceEndorsement'),
                fn () => $this->sourceEndorsement ? [
                    'endorsement_id' => $this->sourceEndorsement->id,
                    'reason' => $this->sourceEndorsement->reason,
                    'from_dept' => $this->sourceEndorsement->oldDepartment?->dept_name,
                    'approved_by' => $this->sourceEndorsement->reviewer?->full_name,
                    'approved_at' => $this->sourceEndorsement->reviewed_at?->format('M d, Y h:i A'),
                ] : null
            ),

            // Timestamps
            'reported_at' => $this->reported_at?->format('M d, Y h:i A'),
            'started_at' => $this->started_at?->format('M d, Y h:i A'),
            'resolved_at' => $this->resolved_at?->format('M d, Y h:i A'),
            'closed_at' => $this->closed_at?->format('M d, Y h:i A'),

            // Exception Workflows: Force Closure
            'is_force_closed' => (bool) ($this->is_force_closed ?? false),
            'closure_reason' => $this->closure_reason,
            'closed_by' => $this->closed_by_id ? [
                'id' => $this->closed_by_id,
                'name' => $this->closedBy?->full_name,
                'role' => $this->closedBy?->role?->role_name,
            ] : null,

            // Exception Workflows: Cancellation
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->format('M d, Y h:i A'),

            // Offline Sync Telemetry
            'is_offline_synced' => (bool) ($this->is_offline_synced ?? false),
            'synced_at' => $this->synced_at?->format('M d, Y h:i A'),
            'client_started_at' => $this->client_started_at?->format('M d, Y h:i A'),

            // Eager-loaded Dispatcher/Creator
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->full_name,
                    'role' => $this->creator->role?->role_name ?? 'Dispatcher',
                ];
            }),

            'consumer' => $this->consumer ? [
                'id' => $this->consumer->id,
                'acct_no' => $this->consumer->acct_no,
                'acct_code' => $this->consumer->acct_code,
                'name' => $this->consumer->name,
                'address' => $this->consumer->address,
                'status' => $this->consumer->status,
                'meter_serial' => $this->consumer->meter_serial,
            ] : null,

            // Grouped Complaint Data
            'complaint' => [
                'source' => $this->complaint_source?->value ?? $this->complaint_source,
                'consumer_contact' => $this->consumer_contact,
                'category_id' => $this->category_id,
                'category_name' => $this->other_category
                    ? $this->other_category_name
                    : ($this->category?->trashed()
                        ? $this->category->category_name.' (Archived)'
                        : $this->category?->category_name),
                'is_other_category' => (bool) $this->other_category,
                'other_category_name' => $this->other_category_name,
                'description' => $this->complaint_description,
                'full_address' => $this->subject,
                'purok' => $this->purok,
                'street' => $this->street,
                'barangay' => $this->barangay,
                'landmark' => $this->landmark,
            ],

            // Eager-loaded Team with Members
            'team' => $this->when($this->team_id !== null, function () {
                if (! $this->team) {
                    return null;
                }

                return [
                    'id' => $this->team->id,
                    'team_name' => $this->team->trashed()
                        ? $this->team->team_name.' (Archived)'
                        : $this->team->team_name,
                    'shift_start' => $this->team->shift_start?->format('H:i'),
                    'shift_end' => $this->team->shift_end?->format('H:i'),
                    'personnel' => $this->team->members ? $this->team->members->map(function ($member) {
                        return [
                            'id' => $member->id,
                            'name' => $member->full_name,
                        ];
                    }) : [],
                ];
            }),

            // Eager-loaded Active Assignment & Dispatch Reason
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

            // Eager-loaded Status Logs for the Timeline
            'status_logs' => $this->whenLoaded('statusLog', function () {
                return $this->statusLog->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'old_status' => $log->old_status?->value ?? $log->old_status,
                        'new_status' => $log->new_status?->value ?? $log->new_status,
                        'changed_by_name' => $log->updater?->full_name,
                        'created_at' => $log->created_at?->format('M d, Y h:i A'),
                    ];
                });
            }),

            // Eager-loaded Child Tickets
            'child_tickets_count' => $this->child_tickets_count ?? 0,
            'child_tickets' => $this->whenLoaded('childTickets', function () {
                return $this->childTickets->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'ticket_number' => $child->ticket_number,
                        'status' => $child->status?->value ?? $child->status,
                    ];
                });
            }),
        ];
    }
}
