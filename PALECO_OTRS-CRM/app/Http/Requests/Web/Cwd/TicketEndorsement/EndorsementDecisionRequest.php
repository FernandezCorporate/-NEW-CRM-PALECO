<?php

namespace App\Http\Requests\Web\Cwd\TicketEndorsement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\EndorsementStatus;

class EndorsementDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRejected = $this->input('status') === EndorsementStatus::REJECTED->value;
        $isApproved = $this->input('status') === EndorsementStatus::APPROVED->value;

        // Fetch the endorsement object injected into the route
        $endorsement = $this->route('endorsement');
        $currentDepartmentId = $endorsement->ticket->department_id;

        return [
            'department_id' => [
                $isApproved ? 'required' : 'nullable',
                Rule::exists('departments', 'id')->whereNull('deleted_at'),
                Rule::notIn([$currentDepartmentId]) // Prevent re-assigning to the same department
            ],
            'status' => [
                'required',
                Rule::enum(EndorsementStatus::class)
            ],
            'rejection_reason' => [
                $isRejected ? 'required' : 'nullable',
                'string',
                'max:1000'
            ]
        ];
    }

    // Custom error messages for better UX
    public function messages(): array
    {
        return [
            'department_id.not_in' => 'The ticket is already assigned to this department. Please select a different target department.',
            'department_id.required' => 'You must select a target department to approve and dispatch this endorsement.',
            'rejection_reason.required' => 'A CWD Note is required when rejecting an endorsement.'
        ];
    }
}