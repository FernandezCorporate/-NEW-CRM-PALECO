<?php

namespace App\Http\Requests\Api\Teams;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_name' => ['required', 'string', 'max:255', Rule::unique('teams', 'team_name')->where(function ($query) {
                return $query->where('department_id', $this->user()->department_id)
                ->whereNull('deleted_at');
                })
            ],
            'team_desc' => ['nullable', 'string', 'max:255'],
            'shift_start' => ['required', 'date_format:H:i'],
            'shift_end' => ['required', 'date_format:H:i'],

            'members' => ['nullable', 'array'],

            // Constraint: Must be a field personnel
            'members.*.user_id' => ['required', Rule::exists('users', 'id')->where(function ($query) {
                $query->whereIn('role_id', function ($subQuery) {
                    $subQuery->select('id')->from('account_roles')->where('slug_identifier', 'field_personnel');
                });
            })],
            'members.*.team_role_id' => ['required', Rule::exists('team_roles', 'id')]
        ];
    }

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
            
            'members.array'                 => 'The members list must be formatted correctly.',
            'members.*.user_id.required'    => 'A user must be selected to be added as a member.',
            'members.*.user_id.exists'      => 'One or more of the selected members are not valid Field Personnel.',
            'members.*.team_role_id.required'=> 'A team role must be assigned to each member.',
            'members.*.team_role_id.exists' => 'The selected team role is invalid.',
        ];
    }
}