<?php

namespace App\Http\Requests\Admin\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
 * Validates incoming HTTP requests for creating a new operational team.
 * Ensures strict constraints on members, requiring them to be valid field personnel.
 */
class StoreTeamRequest extends FormRequest
{
    /*
     * Determines if the user is authorized to make this request.
     * Authorization is handled at the controller level via Policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /*
     * Defines the strict validation rules for creating a team.
     */
    public function rules(): array
    {
        return [
            'team_name' => ['required', 'string', 'max:255', Rule::unique('teams', 'team_name')->whereNull('deleted_at')],
            'team_desc' => ['nullable', 'string', 'max:255'],
            'shift_start' => ['required', 'date_format:H:i'],
            'shift_end' => ['required', 'date_format:H:i'],
            'department_id' => ['required', Rule::exists('departments', 'id')],

            'members' => ['nullable', 'array'],

            'members.*.user_id' => ['required', Rule::exists('users', 'id')->where(function ($query) {
                $query->whereIn('role_id', function ($subQuery) {
                    $subQuery->select('id')->from('account_roles')->where('slug_identifier', 'field_personnel');
                });
            })],
            'members.*.team_role_id' => ['required', Rule::exists('team_roles', 'id')]
        ];
    }

    /*
     * Provides user-friendly error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'team_name.required'      => 'Please provide a name for the team.',
            'team_name.string'        => 'The team name must be a valid text string.',
            'team_name.max'           => 'The team name cannot exceed 255 characters.',
            'team_name.unique'        => 'A team with this name already exists.',
            
            'team_desc.string'        => 'The team description must be a valid text string.',
            'team_desc.max'           => 'The team description cannot exceed 255 characters.',
            
            'shift_start.required'    => 'Please specify the start time for the team\'s shift.',
            'shift_start.date_format' => 'The shift start time must be in a valid 24-hour HH:MM format.',
            
            'shift_end.required'      => 'Please specify the end time for the team\'s shift.',
            'shift_end.date_format'   => 'The shift end time must be in a valid 24-hour HH:MM format.',
            
            'department_id.required'  => 'Please select a department to assign this team to.',
            'department_id.exists'    => 'The selected department does not exist in the system.',
            
            'members.array'                 => 'The members list must be formatted correctly.',
            'members.*.user_id.required'    => 'A user must be selected to be added as a member.',
            'members.*.user_id.exists'      => 'One or more of the selected members are not valid Field Personnel.',
            'members.*.team_role_id.required'=> 'A team role must be assigned to each member.',
            'members.*.team_role_id.exists' => 'The selected team role is invalid.',
        ];
    }
}