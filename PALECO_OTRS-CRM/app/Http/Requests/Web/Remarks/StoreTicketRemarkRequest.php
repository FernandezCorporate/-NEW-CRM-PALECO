<?php

namespace App\Http\Requests\Web\Remarks;

use Illuminate\Foundation\Http\FormRequest;

/*
 * Validates incoming HTTP requests for creating a new ticket remark.
 */
class StoreTicketRemarkRequest extends FormRequest
{
    /*
     * Determines if the user is authorized to make this request.
     * Authorization is handled at the controller level via Policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /*
     * Defines the strict validation rules for creating a remark.
     */
    public function rules(): array
    {
        return [
            'body'        => ['required', 'string', 'min:2', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }

    /*
     * Provides user-friendly error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Please provide the content for this remark.',
            'body.string'   => 'The remark must be valid text.',
            'body.min'      => 'The remark must be at least 2 characters long.',
            'body.max'      => 'The remark cannot exceed 5000 characters.',
            
            'is_internal.boolean' => 'The internal flag must be true or false.',
        ];
    }
}