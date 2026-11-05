<?php

namespace App\Http\Requests\Devices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the Firebase token the mobile app sends after login or when Firebase refreshes it.
 */
class RegisterPushTokenRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'fcm_token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['nullable', Rule::in(['android'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fcm_token.required' => 'The device push token is required.',
            'fcm_token.string' => 'The device push token is not valid.',
            'fcm_token.min' => 'The device push token is not valid.',
            'fcm_token.max' => 'The device push token is too long.',
            'platform.in' => 'Only Android devices are supported.',
        ];
    }
}
