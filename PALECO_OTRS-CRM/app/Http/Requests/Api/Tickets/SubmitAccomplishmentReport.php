<?php

namespace App\Http\Requests\Api\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAccomplishmentReport extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'remarks' => ['required', 'string', 'max:1000'],
            'consumer_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
