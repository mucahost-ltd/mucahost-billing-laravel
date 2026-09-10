<?php

namespace App\Actions\Support;

use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreTicketAttachments
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{name: string, path: string, mime_type: string, size: int}>
     */
    public function handle(Ticket $ticket, array $files): array
    {
        $attachments = [];

        try {
            foreach ($files as $file) {
                $path = $file->store("ticket-attachments/{$ticket->id}", 'local');

                if ($path === false) {
                    throw new RuntimeException('The ticket attachment could not be stored.');
                }

                $attachments[] = [
                    'name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => (int) $file->getSize(),
                ];
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_column($attachments, 'path'));

            throw $exception;
        }

        return $attachments;
    }
}
