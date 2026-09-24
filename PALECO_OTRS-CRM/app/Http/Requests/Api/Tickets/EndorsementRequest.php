<?php

namespace App\Http\Requests\Api\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class EndorsementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'reason'                  => ['required', 'string', 'max:1000'],
            'suggested_department_id' => [
                'nullable', 
                'integer', 
                'exists:departments,id',
                'not_in:'.$this->ticket->department_id,
            ],
        ];
    }

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