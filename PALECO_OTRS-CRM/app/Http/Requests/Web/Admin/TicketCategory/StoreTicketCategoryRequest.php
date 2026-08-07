<?php

namespace App\Http\Requests\Web\Admin\TicketCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
 * Validates incoming HTTP requests for creating a new ticket category.
 * Enforces uniqueness to prevent duplicate system classifications.
 */
class StoreTicketCategoryRequest extends FormRequest
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
     * Defines the strict validation rules for creating a category.
     */
    public function rules(): array
    {
        return [
            'category_name' => ['required', 'string', 'max:100', Rule::unique('ticket_categories', 'category_name')->whereNull('deleted_at')],
            'category_desc' => ['nullable', 'string', 'max:255']
        ];
    }

    /*
     * Provides user-friendly error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'category_name.required' => 'Please provide a name for the ticket category.',
            'category_name.string'   => 'The ticket category name must be a valid text string.',
            'category_name.max'      => 'The ticket category name cannot exceed 100 characters.',
            'category_name.unique'   => 'A ticket category with this name already exists.',
            
            'category_desc.string'   => 'The category description must be a valid text string.',
            'category_desc.max'      => 'The category description cannot exceed 255 characters.',
        ];
    }
}