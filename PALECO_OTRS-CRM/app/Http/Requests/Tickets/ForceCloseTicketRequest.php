<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

/*
 * Validates the administrative force-closure payload for service tickets.
 * Accepts freely typed closure justification without rigid enum constraints.
 */
class ForceCloseTicketRequest extends FormRequest
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
            'closure_reason' => [
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
            'closure_reason.required' => 'Please provide a justification for force-closing this ticket.',
            'closure_reason.min' => 'The closure justification must be at least 3 characters.',
            'closure_reason.max' => 'The closure justification may not exceed 1000 characters.',
        ];
    }
}
