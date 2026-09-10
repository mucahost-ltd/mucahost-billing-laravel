<?php

namespace App\Http\Controllers;

use App\Actions\Support\StoreTicketAttachments;
use App\Http\Requests\Admin\StoreTicketReplyRequest;
use App\Models\SupportDepartment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\Support\TicketReplied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = Ticket::query()
            ->with(['client:id,first_name,last_name,email', 'department:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('department'), fn ($query) => $query->where('department_id', $request->integer('department')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($q) => $q
                ->where('subject', 'like', '%'.$request->string('search').'%')
                ->orWhere('ticket_number', 'like', '%'.$request->string('search').'%')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('support/index', [
            'tickets' => $tickets,
            'departments' => SupportDepartment::query()->withCount('tickets')->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'department', 'priority', 'search']),
        ]);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        return Inertia::render('support/show', [
            'ticket' => $ticket->load(['client:id,first_name,last_name,email', 'department:id,name', 'service:id,domain', 'assignedStaff:id,name']),
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
                            'url' => route('support.attachments.show', [
                                'ticket' => $ticket,
                                'reply' => $reply,
                                'attachment' => $index,
                            ]),
                        ],
                    )->values(),
                ]),
            'staff' => User::query()->where('role', 'admin')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reply(
        StoreTicketReplyRequest $request,
        Ticket $ticket,
        StoreTicketAttachments $storeTicketAttachments,
    ): RedirectResponse {
        $attachments = $storeTicketAttachments->handle($ticket, $request->file('attachments', []));

        $ticket->replies()->create([
            'user_type' => 'staff',
            'user_id' => $request->user('web')->id,
            'message' => $request->validated('message'),
            'attachments' => $attachments ?: null,
        ]);

        $ticket->update(['status' => 'answered']);

        $ticket->client?->notify(new TicketReplied($ticket, $request->validated('message'), 'staff'));

        return back();
    }

    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,answered,customer-reply,closed'],
        ]);

        $ticket->update($data);

        return back();
    }

    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket->update($data);

        return back();
    }
}
