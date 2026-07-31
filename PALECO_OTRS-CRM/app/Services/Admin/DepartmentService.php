<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use App\Models\Department;
use App\Models\User;

class DepartmentService
{
    public function getDashboardDepartments(array $filters)
    {
        $query = Department::query()->withCount([
            'foremen as active_foremen_count',
            'teams as active_team_count',
            'tickets as assigned_ticket_count'
        ]);

        $query->search($filters['search'] ?? null)
              ->sort($filters['sort'] ?? null)
              ->filter($filters['filter'] ?? null);

        return $query->paginate(9)->withQueryString();
    }

    public function getDepartmentDetails(Department $dept): array
    {
        $assignedTeams = $dept->teams()
            ->withCount('members')
            ->paginate(5, ['*'], 'page_teams')
            ->withQueryString();

        $personnelCount = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn($q) => $q->where('slug_identifier', 'field_personnel'))
            ->whereHas('teams', fn($q) => $q->where('department_id', $dept->id))
            ->count();

        $foremanQuery = $dept->foremen()->where('is_active', true);
        $assignedForeman = $foremanQuery->paginate(5, ['*'], 'page_foreman')->withQueryString();

        $assignedTickets = $dept->tickets()->latest('reported_at')->paginate(5, ['*'], 'page_tickets')->withQueryString();
        
        return [
            'assignedTickets' => $assignedTickets,
            'assignedTeams' => $assignedTeams,
            'personnelCount' => $personnelCount,
            'foremanCount' => $foremanQuery->count(),
            'assignedForeman' => $assignedForeman
        ];
    }

    public function updateDepartment(Department $dept, array $data): array
    {
        $originalUpdatedAt = $data['original_updated_at'];
        unset($data['original_updated_at']);

        return DB::transaction(function () use ($dept, $data, $originalUpdatedAt) {
            $lockedDept = Department::where('id', $dept->id)->lockForUpdate()->first();

            if ((string) $lockedDept->updated_at !== $originalUpdatedAt) {
                return [
                    'success' => false, 
                    'message' => 'Conflict: Another administrator modified this department while you were editing. Please refresh and try again.'
                ];
            }

            $lockedDept->fill($data);
            
            if ($lockedDept->isClean()) return ['success' => true, 'changed' => false];
            
            $lockedDept->save(); 
            
            return ['success' => true, 'changed' => true];
        });
    }

    public function archiveDepartment(Department $dept): array
    {
        if ($dept->teams()->exists() || $dept->users()->exists() || $dept->tickets()->exists()) {
            return ['success' => false, 'message' => 'Cannot archive this department. It contains active teams, personnel, or history.'];
        }

        $dept->delete();
        return ['success' => true, 'message' => 'Department archived successfully.'];
    }

    public function restoreDepartment(int $id): array
    {
        $dept = Department::withTrashed()->find($id);
        
        if (!$dept) {
            return ['success' => false, 'message' => 'Department no longer exists. It may have been permanently deleted by another administrator.'];
        }

        if (!$dept->trashed()) {
            return ['success' => false, 'message' => 'Department is already active.'];
        }

        if (Department::where('dept_name', $dept->dept_name)->exists()) {
            return ['success' => false, 'message' => 'Cannot restore department. An active department with the same name already exists.'];
        }

        $dept->restore();
        return ['success' => true, 'message' => 'Department restored successfully.'];
    }

    public function permanentlyDeleteDepartment(int $id): array
    {
        $dept = Department::withTrashed()->find($id);

        if (!$dept) {
            return ['success' => true, 'message' => 'Department has already been permanently deleted.'];
        }

        if (!$dept->trashed()) {
            return ['success' => false, 'message' => 'Cannot permanently delete this department because it was recently restored.'];
        }

        if ($dept->tickets()->exists() || $dept->teams()->exists() || $dept->users()->exists()) {
            return ['success' => false, 'message' => 'Cannot permanently delete this department. It contains historical data.'];
        }

        $dept->forceDelete();
        return ['success' => true, 'message' => 'Department permanently deleted.'];
    }
}