<?php

namespace App\Http\Requests\Api\Remarks;

use Illuminate\Foundation\Http\FormRequest;

/*
 * Validates the creation payload for a mobile ticket remark.
 * Enforces body length constraints and data sanitization.
 */
class StoreTicketRemarkRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }

    /*
     * Customize the error messages for specific rule violations.
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Please provide the content for your remark.',
            'body.string' => 'The remark must be valid text.',
            'body.min' => 'The remark must be at least 2 characters long.',
            'body.max' => 'The remark cannot exceed 5000 characters.',
        ];
    }
}
