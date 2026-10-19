<?php

namespace App\Services\Departments;

use App\Actions\Departments\ArchiveDepartment;
use App\Actions\Departments\CreateDepartment;
use App\Actions\Departments\ForceDeleteDepartment;
use App\Actions\Departments\RestoreDepartment;
use App\Actions\Departments\UpdateDepartment;
use App\Models\Department;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service encapsulating query aggregators and details for Departments.
 * Delegates mutations to dedicated Action classes:
 * - CreateDepartment
 * - UpdateDepartment
 * - ArchiveDepartment
 * - RestoreDepartment
 * - ForceDeleteDepartment
 */
class DepartmentService
{
    public function __construct(
        protected CreateDepartment $createDepartmentAction,
        protected UpdateDepartment $updateDepartmentAction,
        protected ArchiveDepartment $archiveDepartmentAction,
        protected RestoreDepartment $restoreDepartmentAction,
        protected ForceDeleteDepartment $forceDeleteDepartmentAction
    ) {}

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

    // --- MUTATING METHODS ---

    /**
     * Creates a new department record.
     */
    public function createDepartment(array $data): Department
    {
        return $this->createDepartmentAction->execute($data);
    }

    /**
     * Updates department information using optimistic locking concurrency control.
     */
    public function updateDepartment(Department $dept, array $data): array
    {
        return $this->updateDepartmentAction->execute($dept, $data);
    }

    /**
     * Soft-deletes (archives) a department if no active teams, personnel, or active tickets are linked.
     */
    public function archiveDepartment(Department $dept): array
    {
        return $this->archiveDepartmentAction->execute($dept);
    }

    /**
     * Restores an archived department back to active status.
     */
    public function restoreDepartment(int $id): array
    {
        return $this->restoreDepartmentAction->execute($id);
    }

    /**
     * Permanently purges a department record from the database.
     */
    public function permanentlyDeleteDepartment(int $id): array
    {
        return $this->forceDeleteDepartmentAction->execute($id);
    }
}
