<?php

namespace App\Http\Requests\Tickets;

use App\Enums\ComplaintSources;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Validates incoming HTTP requests for logging a new service ticket.
 * Manages validation logic regarding dynamic categories, location checks, and CRM linking.
 */
class StoreTicketRequest extends FormRequest
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
        $isLinkConsumer = $this->boolean('link_consumer');

        $this->merge([
            'other_category' => $isOtherChecked,
            'category_id' => $isOtherChecked ? null : $this->input('category_id'),
            'link_consumer' => $isLinkConsumer,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'consumer_contact' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'complaint_source' => ['required', new Enum(ComplaintSources::class)],
            'complaint_description' => ['required', 'string', 'min:5'],

            // Address Details
            'purok' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],

            'department_id' => ['required', 'exists:departments,id'],

            // Dynamic Category Requirements
            'other_category' => ['boolean'],
            'category_id' => ['required_if:other_category,false', 'nullable', 'exists:ticket_categories,id'],
            'other_category_name' => ['required_if:other_category,true', 'nullable', 'string', 'max:255'],

            // Consumer Linking Requirements
            'link_consumer' => ['boolean'],
            'account_code' => [
                'required_if:link_consumer,true',
                'nullable',
                'string',
                'regex:/^\d{2}-\d{4}-\d{4}$/',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'complaint_source.required' => 'Please indicate the source of the complaint.',
            'complaint_source.Illuminate\Validation\Rules\Enum' => 'The selected complaint source is invalid.',
            'consumer_contact.regex' => 'The contact number must be a valid 11-digit mobile number (e.g., 09123456789).',

            'complaint_description.required' => 'You must provide a clear description of the utility complaint details.',
            'complaint_description.string' => 'The complaint description must be a valid text string.',
            'complaint_description.min' => 'The complaint description must be at least 5 characters long.',

            'purok.string' => 'The purok must be a valid text string.',
            'purok.max' => 'The purok cannot exceed 255 characters.',

            'street.string' => 'The street must be a valid text string.',
            'street.max' => 'The street cannot exceed 255 characters.',

            'barangay.required' => 'The Barangay selection is required for technical field location routing.',
            'barangay.string' => 'The barangay must be a valid text string.',
            'barangay.max' => 'The barangay cannot exceed 255 characters.',

            'landmark.string' => 'The landmark must be a valid text string.',
            'landmark.max' => 'The landmark cannot exceed 255 characters.',

            'department_id.required' => 'Please select a department to handle this ticket.',
            'department_id.exists' => 'The selected department does not exist.',

            'other_category.boolean' => 'The custom category flag must be correctly set as true or false.',

            'category_id.required_if' => 'Please select an established category from the dropdown menu.',
            'category_id.exists' => 'The selected ticket category does not exist.',

            'other_category_name.required_if' => 'You selected "Other Category". Please write a name for the custom category.',
            'other_category_name.string' => 'The custom category name must be a valid text string.',
            'other_category_name.max' => 'The custom category name cannot exceed 255 characters.',

            'account_code.required_if' => 'Please provide an account code to link this ticket to a consumer.',
            'account_code.regex' => 'The account code must follow the standard format (e.g., 02-0504-8538).',
        ];
    }
}
