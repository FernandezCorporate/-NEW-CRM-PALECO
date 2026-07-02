<?php

namespace App\Http\Requests\Admin\Department;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deptId = $this->route('dept');

        return [
            'dept_name' => ['required', 'string', Rule::unique('departments', 'dept_name')->ignore($deptId)],
            'dept_desc' => ['nullable', 'string'],
        ];
    }
}
