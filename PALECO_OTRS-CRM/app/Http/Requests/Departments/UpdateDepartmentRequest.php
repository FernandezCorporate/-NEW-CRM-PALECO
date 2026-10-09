<?php

namespace App\Http\Requests\Departments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates incoming HTTP requests for updating an existing department.
 */
class UpdateDepartmentRequest extends FormRequest
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
        $dept = $this->route('dept') ?? $this->route('department');
        $deptId = is_object($dept) ? $dept->id : $dept;

        return [
            'original_updated_at' => ['required', 'string'],
            'dept_name' => [
                'required',
                'string',
                Rule::unique('departments', 'dept_name')
                    ->ignore($deptId)
                    ->whereNull('deleted_at'),
            ],
            'dept_desc' => ['nullable', 'string'],
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
