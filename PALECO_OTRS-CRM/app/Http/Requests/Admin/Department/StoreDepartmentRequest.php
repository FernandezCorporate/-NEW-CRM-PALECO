<?php

namespace App\Http\Requests\Admin\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // Add this import

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dept_name' => [
                'required', 
                'string', 
                'max:255', 
                // Only check uniqueness against active (non-deleted) records
                Rule::unique('departments', 'dept_name')->whereNull('deleted_at')
            ],
            'dept_desc' => ['nullable', 'string', 'max:255'],
        ];
    }
}