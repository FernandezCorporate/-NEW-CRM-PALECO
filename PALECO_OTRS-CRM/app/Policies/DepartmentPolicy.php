<?php

namespace App\Policies;

use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function view(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function departmentForm(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function create(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function update(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function deleteConfirm(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function archive(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function delete(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function restore(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function forceDelete(User $user): bool { return $user->role->slug_identifier === 'admin'; }   

    /*
     * viewAny: Determines if the user can view the list of departments.
     * view: Determines if the user can view a specific department's details.
     * departmentForm: Determines if the user can access the department creation or edit forms.
     * create: Determines if the user can create new departments.
     * update: Determines if the user can update an existing department.
     * deleteConfirm: Determines if the user can access the deletion confirmation prompt.
     * archive: Determines if the user can soft-delete (archive) a department.
     * delete: Determines if the user can initiate a standard delete operation.
     * restore: Determines if the user can restore a previously archived department.
     * forceDelete: Determines if the user can permanently delete a department.
     */
}