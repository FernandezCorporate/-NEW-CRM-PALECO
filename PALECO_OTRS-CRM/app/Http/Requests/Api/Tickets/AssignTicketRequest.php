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
        // Must be a foreman to assign teams via mobile
        return $this->user()->role->slug_identifier === 'foreman';
    }

    /*
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'string', 'exists:teams,id'],
        ];
    }

    /*
     * Customize the error messages for specific rule violations.
     */
    public function messages(): array
    {
        return [
            'team_id.exists' => 'The selected team does not exist in the active roster.',
        ];
    }
}