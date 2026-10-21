<?php

namespace App\Actions\Departments;

use App\Events\ReferenceDataUpdated;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketEndorsement;
use App\Models\User;

/**
 * Single-Action: Permanently purges a department record from the database.
 */
class ForceDeleteDepartment
{
    /**
     * @return array{success: bool, message: string}
     */
    public function handle(int $id): array
    {
        return $this->execute($id);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(int $id): array
    {
        $dept = Department::withTrashed()->find($id);

        if (! $dept) {
            return [
                'success' => true,
                'message' => 'Department has already been permanently deleted.',
            ];
        }

        if (! $dept->trashed()) {
            return [
                'success' => false,
                'message' => 'Cannot permanently delete this department because it was recently restored.',
            ];
        }

        // Force delete only undoes a department created by mistake; anything that ever referenced it keeps it alive,
        // including archived teams, inactive users, archived tickets and endorsement history
        $hasHistory = Ticket::withTrashed()->where('department_id', $dept->id)->exists()
            || Team::withTrashed()->where('department_id', $dept->id)->exists()
            || User::where('department_id', $dept->id)->exists()
            || TicketEndorsement::where('suggested_department_id', $dept->id)
                ->orWhere('old_department_id', $dept->id)
                ->orWhere('new_department_id', $dept->id)
                ->exists();

        if ($hasHistory) {
            return [
                'success' => false,
                'message' => 'Cannot permanently delete this department. It contains historical data.',
            ];
        }

        $dept->forceDelete();

        rescue(fn () => ReferenceDataUpdated::dispatch('department', 'deleted', $dept->id, $dept->dept_name), report: true);

        return [
            'success' => true,
            'message' => 'Department permanently deleted.',
        ];
    }
}
