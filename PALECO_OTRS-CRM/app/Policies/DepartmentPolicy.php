<?php

namespace App\Policies;

use App\Models\User;

/*
 * Defines RBAC (Role-based access controls) for each available action on a department resource.
 * Implemented as gate checks at the start of every CRUD method on the DepartmentController.
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function view(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function departmentForm(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function create(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function update(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function deleteConfirm(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function archive(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function restore(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function forceDelete(User $user): bool { return $user->role->slug_identifier === 'admin'; }   

    /*
     * viewAny
     * view:          => Admin only; view all department records.
     * departmentForm => Admin only; access the add or edit department form.
     * create:        => Admin only; add a new department record.
     * update:        => Admin only; edit a department record.
     * deleteConfirm  => Admin only; access the delete confirmation prompt (archive & force delete).
     * archive:       => Admin only; archive a department record.
     * restore:       => Admin only; restore an archived department record.
     * forceDelete:   => Admin only; forcefully remove an archived department record.
     */
}