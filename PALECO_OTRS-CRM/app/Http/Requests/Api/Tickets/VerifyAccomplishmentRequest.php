<?php

namespace App\Http\Requests\Api\Tickets;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\TicketAccomplishmentStatus;
use Illuminate\Validation\Rule;

class VerifyAccomplishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required', 
                'string',
                Rule::in([
                    TicketAccomplishmentStatus::APPROVED->value,
                    TicketAccomplishmentStatus::REJECTED->value
                ]) 
            ],
            'rejection_reason' => [
                'required_if:status,' . TicketAccomplishmentStatus::REJECTED->value, 
                'nullable', 
                'string', 
                'max:500'
            ],
        ];
    }
}