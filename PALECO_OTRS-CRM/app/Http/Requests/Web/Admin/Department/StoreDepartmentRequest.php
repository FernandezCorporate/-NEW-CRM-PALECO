<?php

namespace App\Http\Requests\Web\Admin\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates incoming HTTP requests for creating a new department.
 */
class StoreDepartmentRequest extends FormRequest
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
            'dept_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'dept_name')->whereNull('deleted_at'),
            ],
            'dept_desc' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'dept_name.required' => 'Please provide a name for the department.',
            'dept_name.string' => 'The department name must be a valid text string.',
            'dept_name.max' => 'The department name cannot exceed 255 characters.',
            'dept_name.unique' => 'A department with this name already exists in the system.',

            'dept_desc.string' => 'The department description must be a valid text string.',
            'dept_desc.max' => 'The department description cannot exceed 255 characters.',
        ];
    }
}
