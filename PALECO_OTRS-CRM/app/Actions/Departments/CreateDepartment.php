<?php

namespace App\Actions\Departments;

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

        return Department::create($data);
    }
}
