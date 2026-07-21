<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use App\Models\AccountRole;

/*
 * Validates incoming HTTP requests for creating a new user account.
 * Handles extensive profile data, role-based constraints, and credential security.
 */
class StoreUserRequest extends FormRequest
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
     * Formats specific string inputs to lowercase before validation begins.
     */
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

    /*
     * Defines the strict validation rules for creating a user account.
     * Conditionally requires a department ID if the assigned role is a Foreman.
     */
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
            'role_id' => ['required', 'integer', Rule::exists('account_roles', 'id')],
            'department_id' => [
                Rule::requiredIf(function () {
                    $role = AccountRole::find($this->input('role_id'));
                    return $role && $role->slug_identifier === 'foreman';
                }),
                'nullable', 
                'integer', 
                Rule::exists('departments', 'id')->whereNull('deleted_at')
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /*
     * Provides user-friendly error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'username.required'   => 'Please provide a unique username for the account.',
            'username.string'     => 'The username must be a valid text string.',
            'username.max'        => 'The username cannot exceed 100 characters.',
            'username.unique'     => 'This username is already taken. Please choose another one.',
            
            'first_name.required' => 'The user\'s first name is required.',
            'first_name.string'   => 'The first name must be a valid text string.',
            'first_name.max'      => 'The first name cannot exceed 255 characters.',
            
            'middle_name.string'  => 'The middle name must be a valid text string.',
            'middle_name.max'     => 'The middle name cannot exceed 255 characters.',
            
            'last_name.required'  => 'The user\'s last name is required.',
            'last_name.string'    => 'The last name must be a valid text string.',
            'last_name.max'       => 'The last name cannot exceed 255 characters.',
            
            'name_ext.string'     => 'The name extension must be a valid text string.',
            'name_ext.max'        => 'The name extension cannot exceed 10 characters.',
            
            'email.string'        => 'The email address must be a valid text string.',
            'email.email'         => 'Please provide a properly formatted email address.',
            'email.max'           => 'The email address cannot exceed 255 characters.',
            'email.unique'        => 'This email address is already registered in the system.',
            
            'contact.required'    => 'A contact number is required.',
            'contact.string'      => 'The contact number must be a valid text string.',
            'contact.regex'       => 'The contact number must be a valid Philippine mobile number starting with 09 or +639.',
            
            'role_id.required'    => 'Please select an account role for the user.',
            'role_id.integer'     => 'The selected role must be a valid integer.',
            'role_id.exists'      => 'The selected account role does not exist.',
            
            'department_id.required' => 'A department must be assigned when the user\'s role is set to Foreman.',
            'department_id.integer'    => 'The department ID must be a valid integer.',
            'department_id.exists'     => 'The selected department does not exist.',
            
            'password.required'   => 'A password must be provided for the new account.',
            'password.string'     => 'The password must be a valid text string.',
            'password.min'        => 'The password must be at least 8 characters long.',
            'password.confirmed'  => 'The password confirmation does not match.',
        ];
    }
}