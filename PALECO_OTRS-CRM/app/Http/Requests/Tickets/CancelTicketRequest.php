<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

/*
 * Validates the cancellation payload when a CWD officer cancels an Open service ticket.
 * Allows freely typed cancellation reasons without rigid enum constraints.
 */
class CancelTicketRequest extends FormRequest
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
        return [
            'cancellation_reason' => [
                'required',
                'string',
                'min:3',
                'max:1000',
            ],
        ];
    }

    /*
     * Customize the error messages for specific rule violations.
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Please provide a reason for cancelling this ticket.',
            'cancellation_reason.min' => 'The cancellation reason must be at least 3 characters.',
            'cancellation_reason.max' => 'The cancellation reason may not exceed 1000 characters.',
        ];
    }
}
