<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
 * Validates the payload when a supervisor assigns or reassigns a ticket to a team.
 * Enforces cross-department isolation and soft-delete exclusion on the target team.
 */
class AssignTicketRequest extends FormRequest
{
    /*
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /*
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $ticket = $this->route('ticket');
        $isReassignment = $ticket && ! is_null($ticket->team_id);
        $deptId = $ticket?->department_id;

        return [
            'team_id' => [
                'required',
                'string',
                Rule::exists('teams', 'id')
                    ->where(function ($query) use ($deptId) {
                        $query->whereNull('deleted_at');
                        if ($deptId) {
                            $query->where('department_id', $deptId);
                        }
                    }),
            ],
            'reason' => [$isReassignment ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }

    /*
     * Customize the error messages for specific rule violations.
     */
    public function messages(): array
    {
        return [
            'team_id.required' => 'A team must be selected for assignment.',
            'team_id.exists' => 'The selected team does not exist in the active roster for this department.',
            'reason.required' => 'A reason for reassigning the ticket is required.',
            'reason.max' => 'The reason for reassigning the ticket may not exceed 1000 characters.',
        ];
    }
}
