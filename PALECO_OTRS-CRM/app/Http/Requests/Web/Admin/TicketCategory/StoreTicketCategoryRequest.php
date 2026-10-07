<?php

namespace App\Http\Requests\Web\Admin\TicketCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates incoming HTTP requests for creating a new ticket category.
 * Enforces uniqueness to prevent duplicate system classifications.
 */
class StoreTicketCategoryRequest extends FormRequest
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
            'category_name' => ['required', 'string', 'max:100', Rule::unique('ticket_categories', 'category_name')->whereNull('deleted_at')],
            'category_desc' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_name.required' => 'Please provide a name for the ticket category.',
            'category_name.string' => 'The ticket category name must be a valid text string.',
            'category_name.max' => 'The ticket category name cannot exceed 100 characters.',
            'category_name.unique' => 'A ticket category with this name already exists.',

            'category_desc.string' => 'The category description must be a valid text string.',
            'category_desc.max' => 'The category description cannot exceed 255 characters.',
        ];
    }
}
