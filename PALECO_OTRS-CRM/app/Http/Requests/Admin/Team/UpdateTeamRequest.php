<?php

namespace App\Http\Requests\Admin\Team;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\UserRoles;
use App\Enums\TeamMemberRoles;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $team = $this->route('team')->id;

        return [
            'team_name' => ['required', 'string', 'max:255', Rule::unique('teams', 'team_name')->whereNull('deleted_at')->ignore($team)],
            'team_desc' => ['nullable', 'string', 'max:255'],
            'shift_start' => ['required', 'date_format:H:i'],
            'shift_end' => ['required', 'date_format:H:i'],
            'department_id' => ['required', Rule::exists('departments', 'id')],

            'members' => ['nullable', 'array'],

            'members.*.user_id' => ['required', Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role', UserRoles::FIELD_PERSONNEL);  
            })],
            'members.*.team_role' => ['required', Rule::enum(TeamMemberRoles::class)]
        ];
    }

    public function messages(): array
    {
        return [
            'members.*.user_id.exists' => 'One or more of the selected members are not valid Field Personnel.',
        ];
    }
}
