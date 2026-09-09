<?php

namespace App\Http\Requests\Api\Remarks;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRemarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'body'        => ['required', 'string', 'min:2', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Please provide the content for your remark.',
            'body.string'   => 'The remark must be valid text.',
            'body.min'      => 'The remark must be at least 2 characters long.',
            'body.max'      => 'The remark cannot exceed 5000 characters.',
        ];
    }
}