<?php

namespace App\Http\Controllers\Client;

use App\Actions\Support\StoreTicketAttachments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreTicketReplyRequest;
use App\Http\Requests\Client\StoreTicketRequest;
use App\Models\SupportDepartment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\Support\TicketCreated;
use App\Notifications\Support\TicketReplied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $client = $request->user('client');

        return Inertia::render('client/ticket/index', [
            'tickets' => $client->tickets()
                ->with('department:id,name')
                ->latest()
                ->get(['id', 'ticket_number', 'subject', 'status', 'priority', 'department_id', 'created_at']),
        ]);
    }

    public function create(): Response
    {
        $client = request()->user('client');

        return Inertia::render('client/ticket/create', [
            'departments' => SupportDepartment::query()->orderBy('name')->get(['id', 'name']),
            'services' => $client->services()
                ->where('status', 'active')
                ->with('product:id,name')
                ->get(['id', 'product_id', 'domain']),
        ]);
    }

    public function store(StoreTicketRequest $request, StoreTicketAttachments $storeTicketAttachments): RedirectResponse
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-'.Str::ulid(),
            'client_id' => $request->user('client')->id,
            'department_id' => $request->integer('department_id'),
            'service_id' => $request->validated('service_id'),
            'subject' => $request->validated('subject'),
            'status' => 'open',
            'priority' => $request->validated('priority'),
        ]);

        $attachments = $storeTicketAttachments->handle($ticket, $request->file('attachments', []));

        $ticket->replies()->create([
            'user_type' => 'client',
            'user_id' => $request->user('client')->id,
            'message' => $request->validated('message'),
            'attachments' => $attachments ?: null,
        ]);

        Notification::send(
            User::query()->where('role', 'admin')->get(),
            new TicketCreated($ticket->load('client')),
        );

        return redirect()->route('client.tickets.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        abort_unless($ticket->client_id === $request->user('client')->id, 404);

        return Inertia::render('client/ticket/show', [
            'ticket' => $ticket->load('department:id,name', 'service:id,domain'),
            'replies' => $ticket->replies()
                ->orderBy('created_at')
                ->get(['id', 'ticket_id', 'user_type', 'user_id', 'message', 'attachments', 'created_at'])
                ->map(fn (TicketReply $reply): array => [
                    'id' => $reply->id,
                    'user_type' => $reply->user_type,
                    'user_id' => $reply->user_id,
                    'message' => $reply->message,
                    'created_at' => $reply->created_at,
                    'attachments' => collect($reply->attachments ?? [])->map(
                        fn (array $attachment, int $index): array => [
                            'name' => $attachment['name'],
                            'size' => $attachment['size'],
                            'mime_type' => $attachment['mime_type'],
                            'url' => route('client.tickets.attachments.show', [
                                'ticket' => $ticket,
                                'reply' => $reply,
                                'attachment' => $index,
                            ]),
                        ],
                    )->values(),
                ]),
        ]);
    }

    public function reply(
        StoreTicketReplyRequest $request,
        Ticket $ticket,
        StoreTicketAttachments $storeTicketAttachments,
    ): RedirectResponse {
        abort_unless($ticket->client_id === $request->user('client')->id, 404);
        abort_unless($ticket->status !== 'closed', 409);

        $attachments = $storeTicketAttachments->handle($ticket, $request->file('attachments', []));

        $ticket->replies()->create([
            'user_type' => 'client',
            'user_id' => $request->user('client')->id,
            'message' => $request->validated('message'),
            'attachments' => $attachments ?: null,
        ]);

        $ticket->update(['status' => 'open']);

        $assignee = $ticket->assignedStaff;
        if ($assignee) {
            $assignee->notify(new TicketReplied($ticket, $request->validated('message'), 'client'));
        }

        return back();
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->client_id === $request->user('client')->id, 404);

        $ticket->update(['status' => 'closed']);

        return back();
    }
}
