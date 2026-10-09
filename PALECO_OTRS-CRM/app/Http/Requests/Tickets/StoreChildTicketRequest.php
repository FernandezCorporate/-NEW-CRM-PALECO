<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates incoming HTTP requests for creating a dedicated child ticket.
 * Enforces task specification, dynamic categorization, and location consistency.
 */
class StoreChildTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $isOtherChecked = $this->boolean('other_category');

        $this->merge([
            'other_category' => $isOtherChecked,
            'category_id' => $isOtherChecked ? null : $this->input('category_id'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'exists:departments,id'],
            'complaint_description' => ['required', 'string', 'min:5'],

            'other_category' => ['boolean'],
            'category_id' => ['required_if:other_category,false', 'nullable', 'exists:ticket_categories,id'],
            'other_category_name' => ['required_if:other_category,true', 'nullable', 'string', 'max:255'],

            'consumer_contact' => ['nullable', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'purok' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'department_id.required' => 'Please select a department to handle this child ticket.',
            'department_id.exists' => 'The selected department does not exist.',

            'complaint_description.required' => 'You must provide a clear task description for the child ticket.',
            'complaint_description.string' => 'The task description must be a valid text string.',
            'complaint_description.min' => 'The task description must be at least 5 characters long.',

            'other_category.boolean' => 'The custom category flag must be correctly set as true or false.',
            'category_id.required_if' => 'Please select an established category from the dropdown menu.',
            'category_id.exists' => 'The selected ticket category does not exist.',
            'other_category_name.required_if' => 'You selected "Other Category". Please write a name for the custom category.',
            'other_category_name.string' => 'The custom category name must be a valid text string.',
            'other_category_name.max' => 'The custom category name cannot exceed 255 characters.',

            'consumer_contact.regex' => 'The contact number must be a valid 11-digit mobile number (e.g., 09123456789).',
            'barangay.required' => 'The Barangay selection is required for technical field location routing.',
        ];
    }
}
