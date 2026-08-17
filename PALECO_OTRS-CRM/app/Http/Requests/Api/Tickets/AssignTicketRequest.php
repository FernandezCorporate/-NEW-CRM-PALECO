<?php

namespace App\Http\Requests\Api\Tickets;

use Illuminate\Foundation\Http\FormRequest;

/*
 * Validates the payload when a Foreman assigns or reassigns a ticket to a team.
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
        $isReassignment = !is_null($ticket->team_id);

        return [
            'team_id' => ['required', 'string', 'exists:teams,id'],
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
            'team_id.exists' => 'The selected team does not exist in the active roster.',
            'reason.required' => 'A reason for reassigning the ticket is required.',
            'reason.max' => 'The reason for reassigning the ticket may not exceed 1000 characters.',
        ];
    }
}