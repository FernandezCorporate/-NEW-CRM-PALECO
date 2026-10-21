<?php

namespace App\Actions\Departments;

use App\Events\ReferenceDataUpdated;
use App\Models\Department;

/**
 * Single-Action: Stores a newly created department in the database.
 */
class CreateDepartment
{
    public function handle(array $data): Department
    {
        return $this->execute($data);
    }

    public function execute(array $data): Department
    {
        unset($data['original_updated_at']);

        $department = Department::create($data);

        rescue(fn () => ReferenceDataUpdated::dispatch('department', 'created', $department->id, $department->dept_name), report: true);

        return $department;
    }
}
