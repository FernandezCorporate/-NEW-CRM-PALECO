<?php

namespace App\Http\Requests\Api\Tickets;

use Illuminate\Foundation\Http\FormRequest;

/*
 * Validates the payload when a supervisor submits a ticket endorsement request.
 * Ensures the target department exists and cannot match the originating department.
 */
class StoreEndorsementRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:1000'],
            'suggested_department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
                'not_in:'.$this->route('ticket')?->department_id,
            ],
        ];
    }

    /*
     * Customize the error messages for specific rule violations.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for the endorsement request.',
            'reason.max' => 'The reason for the endorsement request may not exceed 1000 characters.',
            'suggested_department_id.exists' => 'The suggested department does not exist in the active roster.',
            'suggested_department_id.not_in' => 'The suggested department cannot be the same as the current department.',
        ];
    }
}
