<?php

namespace App\Actions\Departments;

use App\Models\Department;

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

        if ($dept->tickets()->exists() || $dept->teams()->exists() || $dept->supervisors()->exists()) {
            return [
                'success' => false,
                'message' => 'Cannot permanently delete this department. It contains historical data.',
            ];
        }

        $dept->forceDelete();

        return [
            'success' => true,
            'message' => 'Department permanently deleted.',
        ];
    }
}

