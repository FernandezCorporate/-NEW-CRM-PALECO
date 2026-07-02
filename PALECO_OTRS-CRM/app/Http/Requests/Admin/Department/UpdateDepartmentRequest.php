<?php

namespace App\Http\Requests\Admin\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extract the ID from the bound model
        $deptId = $this->route('dept')->id;

        return [
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
}