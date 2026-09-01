<?php

namespace App\Http\Requests\Api\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAccomplishmentReport extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'remarks' => ['required', 'string', 'max:1000'],
            'consumer_name' => ['nullable', 'string', 'max:255'],
            
            // Exactly one signature image (Max 5MB)
            'signature' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:5120'], 
            
            // Array of photos, requiring at least 1, capped at 10
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            
            // Validate each individual file inside the photos array (Max 10MB each)
            'photos.*' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:10240'], 
        ];
    }

    public function messages(): array
    {
        return [
            'signature.required' => 'An e-signature is required to complete the report.',
            'photos.required' => 'At least one photo evidence must be attached.',
            'photos.*.image' => 'All evidence files must be valid images (JPEG/PNG).',
        ];
    }
}