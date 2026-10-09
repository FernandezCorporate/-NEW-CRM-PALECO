<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates incoming HTTP requests for creating a new ticket remark.
 */
class StoreTicketRemarkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Please provide the content for this remark.',
            'body.string' => 'The remark must be valid text.',
            'body.min' => 'The remark must be at least 2 characters long.',
            'body.max' => 'The remark cannot exceed 5000 characters.',
            'is_internal.boolean' => 'The internal flag must be true or false.',
        ];
    }
}
