<?php

namespace App\Services\Admin;

use App\Models\Department;
use App\Models\User;

class DepartmentService
{
    public function getDashboardDepartments(array $filters)
    {
        $query = Department::query()->withCount([
            'users as active_foremen_count' => function ($query) {
                $query->where('is_active', true)->whereHas('role', fn($q) => $q->where('slug_identifier', 'foreman'));
            },
            'teams as active_team_count'
        ]);

        $query->search($filters['search'] ?? null)
              ->sort($filters['sort'] ?? null);

        if (($filters['filter'] ?? null) === 'archived') {
            $query->onlyTrashed();
        }

        return $query->paginate(9)->withQueryString();
    }

    public function getDepartmentDetails(Department $dept): array
    {
        $assignedTeams = $dept->teams()->withCount('members')->paginate(5, ['*'], 'page');

        $personnelCount = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn($q) => $q->where('slug_identifier', 'field_personnel'))
            ->whereHas('teams', fn($q) => $q->where('department_id', $dept->id))
            ->count();

        $foremanQuery = $dept->users()->where('is_active', true)
            ->whereHas('role', fn($q) => $q->where('slug_identifier', 'foreman'));
        
        return [
            'assignedTeams' => $assignedTeams,
            'personnelCount' => $personnelCount,
            'foremanCount' => $foremanQuery->count(),
            'foremanCollection' => $foremanQuery->paginate(5, ['*'], 'page_foreman')
        ];
    }

    public function updateDepartment(Department $dept, array $data): bool
    {
        $dept->fill($data);
        if ($dept->isClean()) return false;
        
        $dept->save();
        return true;
    }

    public function restoreDepartment(int $id): array
    {
        $dept = Department::onlyTrashed()->findOrFail($id);

        if (Department::where('dept_name', $dept->dept_name)->exists()) {
            return ['success' => false, 'message' => 'Cannot restore department. An active department with the same name already exists.'];
        }

        $dept->restore();
        return ['success' => true, 'message' => 'Department restored successfully.'];
    }
}