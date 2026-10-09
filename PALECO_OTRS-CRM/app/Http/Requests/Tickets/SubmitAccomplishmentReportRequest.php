<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

/*
 * Validates the submission payload for a ticket accomplishment report.
 * Enforces image mime types, file sizes, and e-signature presence.
 */
class SubmitAccomplishmentReportRequest extends FormRequest
{
    /*
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /*
     * Get the validation rules that apply to the request.
     */
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

    /*
     * Customize the error messages for specific rule violations.
     */
    public function messages(): array
    {
        return [
            'signature.required' => 'An e-signature is required to complete the report.',
            'photos.required' => 'At least one photo evidence must be attached.',
            'photos.*.image' => 'All evidence files must be valid images (JPEG/PNG).',
        ];
    }
}
