<?php

namespace App\Http\Requests\Api\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class EscalationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'reason'                  => ['required', 'string', 'max:1000'],
            'suggested_department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }
}