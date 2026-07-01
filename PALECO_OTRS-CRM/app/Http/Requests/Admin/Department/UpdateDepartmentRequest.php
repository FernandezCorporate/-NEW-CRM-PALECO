<?php

namespace App\Http\Requests\Admin\Department;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $deptId = $this->route('dept');

        return [
            'dept_name' => ['required', 'string', 'max:255', 'unique:departments,dept_name,' . $deptId],
            'dept_desc' => ['nullable', 'string'],
        ];
    }
}
