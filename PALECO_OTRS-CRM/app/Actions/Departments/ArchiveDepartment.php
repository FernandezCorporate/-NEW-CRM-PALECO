<?php

namespace App\Actions\Departments;

use App\Enums\TicketStatus;
use App\Models\Department;
use Illuminate\Support\Facades\DB;

/**
 * Single-Action: Soft-deletes (archives) a department if no active teams, personnel, or active tickets are linked.
 */
class ArchiveDepartment
{
    /**
     * @return array{success: bool, message: string}
     */
    public function handle(Department $dept): array
    {
        return $this->execute($dept);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(Department $dept): array
    {
        return DB::transaction(function () use ($dept) {
            $lockedDept = Department::where('id', $dept->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedDept || $lockedDept->trashed()) {
                return [
                    'success' => false,
                    'message' => 'Conflict: This department has already been archived by an administrator.',
                ];
            }

            $hasActiveTickets = $lockedDept->tickets()
                ->whereIn('status', [
                    TicketStatus::OPEN,
                    TicketStatus::ASSIGNED,
                    TicketStatus::IN_PROGRESS,
                    TicketStatus::PENDING_ENDORSEMENT,
                ])
                ->exists();

            $hasActiveTeams = $lockedDept->teams()->whereNull('deleted_at')->exists();
            $hasActiveSupervisors = $lockedDept->supervisors()->where('is_active', true)->exists();

            if ($hasActiveTickets || $hasActiveTeams || $hasActiveSupervisors) {
                return [
                    'success' => false,
                    'message' => 'Cannot archive this department. It contains active teams, personnel, or active service tickets.',
                ];
            }

            $lockedDept->delete();

            return [
                'success' => true,
                'message' => 'Department archived successfully.',
            ];
        });
    }
}

