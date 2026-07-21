<?php

namespace App\Services\Admin;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;

/*
 * Encapsulates the business logic for managing Department records.
 * Offloads heavy data aggregation and state manipulation from the controller.
 */
class DepartmentService
{
    // --- VIEW DATA AGGREGATION ---

    /*
     * Retrieves a paginated list of departments for the dashboard.
     * Appends statistical counts (active foremen and teams) and applies search/sort filters.
     */
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

    /*
     * Compiles comprehensive details for a specific department's profile view.
     * Aggregates paginated teams, total personnel count, and active foremen lists.
     */
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

    // --- MUTATING & STATE METHODS ---

    /*
     * Updates the attributes of an existing department.
     * Returns a boolean indicating whether any actual data changes were committed.
     */
    public function updateDepartment(Department $dept, array $data): bool
    {
        $dept->fill($data);
        if ($dept->isClean()) return false;
        
        $dept->save();
        return true;
    }

    /*
     * Attempts to restore a soft-deleted department.
     * Enforces uniqueness checks to prevent restoring a department if a new one shares its name.
     */
    public function restoreDepartment(int $id): array
    {
        $dept = Department::onlyTrashed()->findOrFail($id);

        if (Department::where('dept_name', $dept->dept_name)->exists()) {
            return ['success' => false, 'message' => 'Cannot restore department. An active department with the same name already exists.'];
        }

        $dept->restore();
        return ['success' => true, 'message' => 'Department restored successfully.'];
    }

    // --- DESTRUCTIVE METHODS ---

    /*
     * Proactively checks for relational dependencies before allowing permanent deletion.
     * Prevents SQL foreign key constraint errors by blocking the deletion of referenced departments.
     */
    public function permanentlyDeleteDepartment(int $id): array
    {
        $dept = Department::onlyTrashed()->findOrFail($id);

        // Proactive Relationship Checks
        $hasTickets = Ticket::where('department_id', $dept->id)->exists();
        $hasTeams = $dept->teams()->exists();
        $hasUsers = $dept->users()->exists();

        if ($hasTickets || $hasTeams || $hasUsers) {
            return [
                'success' => false, 
                'message' => 'Cannot permanently delete this department because it is currently referenced by historical tickets, active teams, or assigned personnel.'
            ];
        }

        $dept->forceDelete();
        
        return [
            'success' => true, 
            'message' => 'Department permanently deleted.'
        ];
    }
}