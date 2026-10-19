<?php

namespace App\Actions\Departments;

use App\Models\Department;

/**
 * Single-Action: Restores an archived department back to active status.
 */
class RestoreDepartment
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
                'success' => false,
                'message' => 'Department no longer exists. It may have been permanently deleted by another administrator.',
            ];
        }

        if (! $dept->trashed()) {
            return [
                'success' => false,
                'message' => 'Department is already active.',
            ];
        }

        if (Department::where('dept_name', $dept->dept_name)->exists()) {
            return [
                'success' => false,
                'message' => 'Cannot restore department. An active department with the same name already exists.',
            ];
        }

        $dept->restore();

        return [
            'success' => true,
            'message' => 'Department restored successfully.',
        ];
    }
}

