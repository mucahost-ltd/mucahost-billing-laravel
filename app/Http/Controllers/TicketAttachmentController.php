<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function showForClient(Request $request, Ticket $ticket, TicketReply $reply, int $attachment): StreamedResponse
    {
        abort_unless($ticket->client_id === $request->user('client')->id, 404);

        return $this->download($ticket, $reply, $attachment);
    }

    public function showForAdmin(Ticket $ticket, TicketReply $reply, int $attachment): StreamedResponse
    {
        return $this->download($ticket, $reply, $attachment);
    }

    private function download(Ticket $ticket, TicketReply $reply, int $attachment): StreamedResponse
    {
        abort_unless($reply->ticket_id === $ticket->id, 404);

        $metadata = ($reply->attachments ?? [])[$attachment] ?? null;
        abort_unless(is_array($metadata), 404);

        $path = $metadata['path'] ?? null;
        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download(
            $path,
            (string) ($metadata['name'] ?? 'attachment'),
            [
                'Content-Type' => (string) ($metadata['mime_type'] ?? 'application/octet-stream'),
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
