<?php

namespace App\Http\Requests\Web\Cwd\TicketEscalation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\EscalationStatus;

class EscalationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRejected = $this->input('status') === EscalationStatus::REJECTED->value;
        $isApproved = $this->input('status') === EscalationStatus::APPROVED->value;

        // Fetch the escalation object injected into the route
        $escalation = $this->route('escalation');
        $currentDepartmentId = $escalation->ticket->department_id;

        return [
            'department_id' => [
                $isApproved ? 'required' : 'nullable',
                Rule::exists('departments', 'id')->whereNull('deleted_at'),
                Rule::notIn([$currentDepartmentId]) // Prevent re-assigning to the same department
            ],
            'status' => [
                'required',
                Rule::enum(EscalationStatus::class)
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
            'department_id.required' => 'You must select a target department to approve and dispatch this escalation.',
            'rejection_reason.required' => 'A CWD Note is required when rejecting an escalation.'
        ];
    }
}