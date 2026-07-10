<?php

namespace App\Http\Requests\Admin\TicketCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_name' => [
                'required', 
                'string', 
                'max:100', 
                Rule::unique('ticket_categories', 'category_name')
                    ->ignore($this->route('category'))
                    ->whereNull('deleted_at')
            ],
            'category_desc' => ['nullable', 'string', 'max:255']
        ];
    }
}