<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\UserRoles;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
        $user = $this->route('user')->id;

        return [
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'name_ext' => ['nullable', 'string', 'max:10'],
            'email' => ['nullable', 'string', 'email:rfc,dns', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'contact' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'role' => ['required', 'string', Rule::enum(UserRoles::class)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'contact.regex' => 'The contact number must be a valid Philippine mobile number starting with 09 or +639.',
        ];
    }
}
