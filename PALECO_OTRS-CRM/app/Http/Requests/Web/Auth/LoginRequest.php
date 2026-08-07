<?php

namespace App\Http\Requests\Web\Auth;

use Illuminate\Foundation\Http\FormRequest;

/*
 * Validates incoming authentication attempts.
 * Ensures the basic required fields for login are present and sanitized.
 */
class LoginRequest extends FormRequest
{
    /*
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /*
     * Sanitizes the username input by trimming whitespace before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => trim($this->username),
        ]);
    }

    /*
     * Defines the strict validation rules for the login form.
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /*
     * Provides user-friendly error messages for authentication inputs.
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Please enter your username to continue.',
            'username.string'   => 'The username format is invalid.',
            
            'password.required' => 'Please enter your password to log in.',
            'password.string'   => 'The password format is invalid.',
        ];
    }
}