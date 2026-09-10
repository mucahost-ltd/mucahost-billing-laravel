<?php

namespace App\Http\Requests\Admin;

use App\Concerns\TicketAttachmentValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketReplyRequest extends FormRequest
{
    use TicketAttachmentValidationRules;

    public function authorize(): bool
    {
        return $this->user('web')?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:10000'],
            ...$this->ticketAttachmentRules(),
        ];
    }
}
