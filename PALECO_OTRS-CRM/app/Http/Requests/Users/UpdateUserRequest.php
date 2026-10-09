<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates incoming HTTP requests for updating an existing user account.
 * Roles and passwords are excluded here, maintaining strict role-based department checks.
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => $this->filled('username') ? $this->string('username')->lower()->toString() : null,
            'first_name' => $this->filled('first_name') ? $this->string('first_name')->lower()->toString() : null,
            'middle_name' => $this->filled('middle_name') ? $this->string('middle_name')->lower()->toString() : null,
            'last_name' => $this->filled('last_name') ? $this->string('last_name')->lower()->toString() : null,
            'name_ext' => $this->filled('name_ext') ? $this->string('name_ext')->lower()->toString() : null,
            'email' => $this->filled('email') ? $this->string('email')->lower()->toString() : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userModel = $this->route('user');

        return [
            'original_updated_at' => ['required', 'string'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($userModel->id)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'name_ext' => ['nullable', 'string', 'max:10'],
            'email' => ['nullable', 'string', 'email:rfc,dns', 'max:255', Rule::unique('users', 'email')->ignore($userModel->id)],
            'contact' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'department_id' => [
                Rule::requiredIf(function () use ($userModel) {
                    return $userModel && $userModel->role->slug_identifier === 'supervisor';
                }),
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Please provide a unique username for the account.',
            'username.string' => 'The username must be a valid text string.',
            'username.max' => 'The username cannot exceed 100 characters.',
            'username.unique' => 'This username is already taken. Please choose another one.',

            'first_name.required' => 'The user\'s first name is required.',
            'first_name.string' => 'The first name must be a valid text string.',
            'first_name.max' => 'The first name cannot exceed 255 characters.',

            'middle_name.string' => 'The middle name must be a valid text string.',
            'middle_name.max' => 'The middle name cannot exceed 255 characters.',

            'last_name.required' => 'The user\'s last name is required.',
            'last_name.string' => 'The last name must be a valid text string.',
            'last_name.max' => 'The last name cannot exceed 255 characters.',

            'name_ext.string' => 'The name extension must be a valid text string.',
            'name_ext.max' => 'The name extension cannot exceed 10 characters.',

            'email.string' => 'The email address must be a valid text string.',
            'email.email' => 'Please provide a properly formatted email address.',
            'email.max' => 'The email address cannot exceed 255 characters.',
            'email.unique' => 'This email address is already registered in the system.',

            'contact.required' => 'A contact number is required.',
            'contact.string' => 'The contact number must be a valid text string.',
            'contact.regex' => 'The contact number must be a valid Philippine mobile number starting with 09 or +639.',

            'department_id.requiredIf' => 'A department must be assigned since the user\'s role is Supervisor.',
            'department_id.integer' => 'The department ID must be a valid integer.',
            'department_id.exists' => 'The selected department does not exist.',
        ];
    }
}
