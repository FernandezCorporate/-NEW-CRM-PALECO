<?php

namespace App\Http\Requests\Admin\Department;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
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
        return [
            'dept_name' => ['required', 'string', 'max:255', 'unique:departments,dept_name'],
            'dept_desc' => ['nullable', 'string', 'max:255'],
        ];
    }
}
