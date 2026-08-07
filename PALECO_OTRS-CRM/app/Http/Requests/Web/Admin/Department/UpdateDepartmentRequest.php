<?php

namespace App\Http\Requests\Web\Admin\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
 * Validates incoming HTTP requests for updating an existing department.
 * Prevents name collisions while ignoring the department's own current name.
 */
class UpdateDepartmentRequest extends FormRequest
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
     * Defines the strict validation rules for updating a department.
     */
    public function rules(): array
    {
        // Extract the ID from the bound model
        $deptId = $this->route('dept')->id;

        return [
            'original_updated_at' => ['required', 'string'],
            'dept_name' => [
                'required', 
                'string', 
                Rule::unique('departments', 'dept_name')
                    ->ignore($deptId)
                    ->whereNull('deleted_at') // Ignore archived records
            ],
            'dept_desc' => ['nullable', 'string'],
        ];
    }

    /*
     * Provides user-friendly error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'dept_name.required' => 'Please provide a name for the department.',
            'dept_name.string'   => 'The department name must be a valid text string.',
            'dept_name.max'      => 'The department name cannot exceed 255 characters.',
            'dept_name.unique'   => 'A department with this name already exists in the system.',
            
            'dept_desc.string'   => 'The department description must be a valid text string.',
            'dept_desc.max'      => 'The department description cannot exceed 255 characters.',
        ];
    }
}