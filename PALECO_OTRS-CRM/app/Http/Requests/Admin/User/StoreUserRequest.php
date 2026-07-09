<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Role;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username'    => $this->filled('username') ? $this->string('username')->lower()->toString() : null,
            'first_name'  => $this->filled('first_name') ? $this->string('first_name')->lower()->toString() : null,
            'middle_name' => $this->filled('middle_name') ? $this->string('middle_name')->lower()->toString() : null,
            'last_name'   => $this->filled('last_name') ? $this->string('last_name')->lower()->toString() : null,
            'name_ext'    => $this->filled('name_ext') ? $this->string('name_ext')->lower()->toString() : null,
            'email'       => $this->filled('email') ? $this->string('email')->lower()->toString() : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'name_ext' => ['nullable', 'string', 'max:10'],
            'email' => ['nullable', 'string', 'email:rfc,dns', 'max:255', Rule::unique('users', 'email')],
            'contact' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereNull('deleted_at')],
            'department_id' => [
                Rule::requiredIf(function () {
                    $role = Role::find($this->input('role_id'));
                    return $role && $role->slug_identifier !== 'field_personnel';
                }),
                'nullable', 
                'integer', 
                Rule::exists('departments', 'id')->whereNull('deleted_at')
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact.regex' => 'The contact number must be a valid Philippine mobile number starting with 09 or +639.',
        ];
    }
}