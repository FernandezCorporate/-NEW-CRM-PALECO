<?php

namespace App\Services\Departments;

use App\Models\Department;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service encapsulating query aggregators and details for Departments.
 */
class DepartmentService
{
    // --- QUERY METHODS ---

    /**
     * Retrieves paginated departments with workload metrics and search filters.
     */
    public function getDashboardDepartments(array $filters): LengthAwarePaginator
    {
        $query = Department::query()->withCount([
            'supervisors as active_supervisors_count',
            'teams as active_team_count',
            'tickets as assigned_ticket_count',
        ]);

        $query->search($filters['search'] ?? null)
            ->sort($filters['sort'] ?? null)
            ->filter($filters['filter'] ?? null);

        return $query->paginate(9)->withQueryString();
    }

    /**
     * Retrieves detailed profile data, teams, supervisors, and service tickets for a department.
     */
    public function getDepartmentDetails(Department $dept): array
    {
        $assignedTeams = $dept->teams()
            ->withCount('members')
            ->paginate(5, ['*'], 'page_teams')
            ->withQueryString();

        $personnelCount = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('slug_identifier', 'field_personnel'))
            ->whereHas('teams', fn ($q) => $q->where('department_id', $dept->id))
            ->count();

        $supervisorQuery = $dept->supervisors()->where('is_active', true);
        $assignedSupervisor = $supervisorQuery->paginate(5, ['*'], 'page_supervisor')->withQueryString();

        $assignedTickets = $dept->tickets()->latest('reported_at')->paginate(5, ['*'], 'page_tickets')->withQueryString();

        return [
            'assignedTickets' => $assignedTickets,
            'assignedTeams' => $assignedTeams,
            'personnelCount' => $personnelCount,
            'supervisorCount' => $supervisorQuery->count(),
            'assignedSupervisor' => $assignedSupervisor,
        ];
    }
}
