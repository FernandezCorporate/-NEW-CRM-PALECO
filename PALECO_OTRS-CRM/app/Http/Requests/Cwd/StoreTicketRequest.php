<?php

namespace App\Http\Requests\Cwd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

use App\Enums\ComplaintSources;

/*
 * Validates incoming HTTP requests for logging a new service ticket.
 * Manages complex validation logic regarding dynamic categories and strict location checks.
 */
class StoreTicketRequest extends FormRequest
{
    /*
     * Determines if the user is authorized to make this request.
     * Strictly verifies the user role belongs to a CWD Officer.
     */
    public function authorize(): bool
    {
        return $this->user()->role->slug_identifier === 'cwd_officer';
    }

    /*
     * Formats specific inputs to resolve UI quirks before validation begins.
     * Nullifies the standard category ID if the user explicitly checks the "Other" flag.
     */
    protected function prepareForValidation(): void
    {
        // Quirks Conversion: Force category_id to null immediately if other_category is true
        $isOtherChecked = $this->boolean('other_category');

        $this->merge([
            'other_category' => $isOtherChecked,
            'category_id' => $isOtherChecked ? null : $this->input('category_id'),
        ]);
    }

    /*
     * Defines the strict validation rules for creating a ticket.
     * Enforces conditional validation depending on the selected categorization path.
     */
    public function rules(): array
    {
        return [
            'complaint_source' => ['required', new Enum(ComplaintSources::class)],
            'complaint_description' => ['required', 'string', 'min:5'],
            
            // Unique Address Rule: Only barangay is strictly required
            'purok' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            
            'department_id' => ['required', 'exists:departments,id'],
            'other_category' => ['boolean'],

            // Dynamic Category Processing Requirements
            'category_id' => [
                'required_if:other_category,false', 
                'nullable', 
                'exists:ticket_categories,id'
            ],
            'other_category_name' => [
                'required_if:other_category,true', 
                'nullable', 
                'string', 
                'max:255'
            ],
        ];
    }

    /*
     * Provides user-friendly error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'complaint_source.required'       => 'Please indicate the source of the complaint.',
            'complaint_source.Illuminate\Validation\Rules\Enum' => 'The selected complaint source is invalid.',
            
            'complaint_description.required'  => 'You must provide a clear description of the utility complaint details.',
            'complaint_description.string'    => 'The complaint description must be a valid text string.',
            'complaint_description.min'       => 'The complaint description must be at least 5 characters long.',
            
            'purok.string'                    => 'The purok must be a valid text string.',
            'purok.max'                       => 'The purok cannot exceed 255 characters.',
            
            'street.string'                   => 'The street must be a valid text string.',
            'street.max'                      => 'The street cannot exceed 255 characters.',
            
            'barangay.required'               => 'The Barangay selection is required for technical field location routing.',
            'barangay.string'                 => 'The barangay must be a valid text string.',
            'barangay.max'                    => 'The barangay cannot exceed 255 characters.',
            
            'landmark.string'                 => 'The landmark must be a valid text string.',
            'landmark.max'                    => 'The landmark cannot exceed 255 characters.',
            
            'department_id.required'          => 'Please select a department to handle this ticket.',
            'department_id.exists'            => 'The selected department does not exist.',
            
            'other_category.boolean'          => 'The custom category flag must be correctly set as true or false.',
            
            'category_id.required_if'         => 'Please select an established category from the dropdown menu.',
            'category_id.exists'              => 'The selected ticket category does not exist.',
            
            'other_category_name.required_if' => 'You selected "Other Category". Please write a name for the custom category.',
            'other_category_name.string'      => 'The custom category name must be a valid text string.',
            'other_category_name.max'         => 'The custom category name cannot exceed 255 characters.',
        ];
    }
}