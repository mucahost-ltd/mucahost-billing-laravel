<?php

namespace App\Concerns;

use Illuminate\Validation\Rules\File;

trait TicketAttachmentValidationRules
{
    /** @return array<string, mixed> */
    protected function ticketAttachmentRules(): array
    {
        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'log', 'zip',
            'doc', 'docx', 'xls', 'xlsx',
        ];

        return [
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                File::types($allowedExtensions)
                    ->extensions($allowedExtensions)
                    ->max('10mb'),
            ],
        ];
    }
}
