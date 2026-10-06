<?php

namespace App\Http\Requests\Api\Tickets;

use App\Enums\TicketAccomplishmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
 * Validates the supervisor's evaluation response for a ticket accomplishment report.
 * Ensures valid status selection and mandates an explanation upon rejection.
 */
class VerifyAccomplishmentRequest extends FormRequest
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
            'status' => [
                'required',
                'string',
                Rule::in([
                    TicketAccomplishmentStatus::APPROVED->value,
                    TicketAccomplishmentStatus::REJECTED->value,
                ]),
            ],
            'rejection_reason' => [
                'required_if:status,'.TicketAccomplishmentStatus::REJECTED->value,
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /*
     * Customize the error messages for specific rule violations.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'A verification status (APPROVED or REJECTED) is required.',
            'status.in' => 'The selected verification status is invalid.',
            'rejection_reason.required_if' => 'A rejection reason is required when rejecting an accomplishment report.',
            'rejection_reason.max' => 'The rejection reason may not exceed 500 characters.',
        ];
    }
}
