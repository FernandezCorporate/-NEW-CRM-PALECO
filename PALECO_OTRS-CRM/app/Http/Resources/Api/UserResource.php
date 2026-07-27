<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Transforms the User model into a standardized JSON payload.
 * Ensures consistent user profile data formatting across all API endpoints (Auth and Profile).
 */
class UserResource extends JsonResource
{
    /*
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'username'      => $this->username,
            'name'          => $this->full_name,
            'first_name'    => $this->first_name,
            'middle_name'   => $this->middle_name,
            'last_name'     => $this->last_name,
            'name_ext'      => $this->name_ext,
            'email'         => $this->email,
            'contact'       => $this->contact,
            'role_slug'     => $this->role?->slug_identifier,
            'role_name'     => $this->role?->role_name,
            'department_id' => $this->department_id,
            'department'    => $this->department?->dept_name,
        ];
    }
}