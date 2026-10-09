<?php

namespace App\Http\Requests\Tickets;

use App\Enums\EndorsementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates incoming HTTP requests for reviewing and deciding on a ticket endorsement.
 */
class EndorsementDecisionRequest extends FormRequest
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
        $isRejected = $this->input('status') === EndorsementStatus::REJECTED->value;
        $isApproved = $this->input('status') === EndorsementStatus::APPROVED->value;

        $endorsement = $this->route('endorsement');
        $currentDepartmentId = $endorsement->ticket->department_id;

        return [
            'department_id' => [
                $isApproved ? 'required' : 'nullable',
                Rule::exists('departments', 'id')->whereNull('deleted_at'),
                Rule::notIn([$currentDepartmentId]),
            ],
            'status' => [
                'required',
                Rule::enum(EndorsementStatus::class),
            ],
            'rejection_reason' => [
                $isRejected ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'department_id.not_in' => 'The ticket is already assigned to this department. Please select a different target department.',
            'department_id.required' => 'You must select a target department to approve and dispatch this endorsement.',
            'rejection_reason.required' => 'A CWD Note is required when rejecting an endorsement.',
        ];
    }
}
