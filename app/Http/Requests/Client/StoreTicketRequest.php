<?php

namespace App\Http\Requests\Client;

use App\Concerns\TicketAttachmentValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    use TicketAttachmentValidationRules;

    public function authorize(): bool
    {
        return $this->user('client') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:support_departments,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'message' => ['required', 'string', 'max:10000'],
            ...$this->ticketAttachmentRules(),
        ];
    }
}
