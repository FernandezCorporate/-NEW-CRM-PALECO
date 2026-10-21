<?php

namespace App\Actions\Users;

use App\Models\AccountRole;
use App\Models\User;

/**
 * Single-Action: Configures and stores a new user account with role-specific constraints.
 */
class CreateUser
{
    public function handle(array $data): User
    {
        return $this->execute($data);
    }

    public function execute(array $data): User
    {
        unset($data['original_updated_at']);

        $role = isset($data['role_id']) ? AccountRole::find($data['role_id']) : null;

        if ($role && $role->slug_identifier === 'field_personnel') {
            $data['department_id'] = null;
        }

        return User::create($data);
    }
}
