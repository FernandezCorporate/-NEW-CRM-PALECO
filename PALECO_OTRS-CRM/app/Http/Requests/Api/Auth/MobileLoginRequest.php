<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

/*
 * Validates incoming authentication attempts from the mobile application.
 * Ensures the basic required credentials and device identification are present.
 */
class MobileLoginRequest extends FormRequest
{
    /*
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; 
    }

    /*
     * Defines the strict validation rules for the mobile login endpoint.
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string'], 
        ];
    }

    /*
     * Provides user-friendly error messages for mobile authentication inputs.
     */
    public function messages(): array
    {
        return [
            'username.required'    => 'Please enter your username to continue.',
            'username.string'      => 'The username format is invalid.',
            
            'password.required'    => 'Please enter your password to log in.',
            'password.string'      => 'The password format is invalid.',
            
            'device_name.required' => 'Device identification is required to establish a secure session.',
            'device_name.string'   => 'The device name format is invalid.',
        ];
    }
}