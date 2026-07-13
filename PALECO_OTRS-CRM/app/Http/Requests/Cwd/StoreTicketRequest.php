<?php

namespace App\Http\Requests\Cwd;

use App\Enums\ComplaintSources;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->slug_identifier === 'cwd_officer';
    }

    protected function prepareForValidation(): void
    {
        // Quirks Conversion: Force category_id to null immediately if other_category is true
        $isOtherChecked = $this->boolean('other_category');

        $this->merge([
            'other_category' => $isOtherChecked,
            'category_id' => $isOtherChecked ? null : $this->input('category_id'),
        ]);
    }

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

    public function messages(): array
    {
        return [
            'category_id.required_if' => 'Please select an established category from the dropdown menu.',
            'other_category_name.required_if' => 'You selected "Other Category". Please write a name for the custom category.',
            'barangay.required' => 'The Barangay selection is required for technical field location routing.',
            'complaint_description.required' => 'You must provide a clear description of the utility complaint details.',
        ];
    }
}