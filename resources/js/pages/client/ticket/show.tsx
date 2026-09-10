import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import {
    TicketAttachmentInput,
    TicketAttachmentList,
    type TicketAttachment,
} from '@/components/ticket-attachments';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { close, reply } from '@/routes/client/tickets';

type Ticket = {
    id: number;
    ticket_number: string;
    subject: string;
    status: string;
    priority: string;
    created_at: string;
    department: { name: string };
    service: { domain: string | null } | null;
};

type Reply = {
    id: number;
    user_type: 'client' | 'staff';
    user_id: number;
    message: string;
    created_at: string;
    attachments: TicketAttachment[];
};

const statusStyles: Record<string, string> = {
    open: 'bg-blue-100 text-blue-700',
    answered: 'bg-green-100 text-green-700',
    'customer-reply': 'bg-amber-100 text-amber-700',
    closed: 'bg-gray-100 text-gray-600',
};

export default function TicketsShow({
    ticket,
    replies,
}: {
    ticket: Ticket;
    replies: Reply[];
}) {
    const form = useForm<{ message: string; attachments: File[] }>({
        message: '',
        attachments: [],
    });
    const closeForm = useForm({});

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(reply.url(ticket.id), {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <>
            <Head title={ticket.subject} />
            <div className="mb-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {ticket.subject}
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            {ticket.ticket_number} · {ticket.department.name}
                            {ticket.service?.domain
                                ? ` · ${ticket.service.domain}`
                                : ''}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span
                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${statusStyles[ticket.status] ?? 'bg-gray-100 text-gray-600'}`}
                        >
                            {ticket.status.replace('-', ' ')}
                        </span>
                        {ticket.status !== 'closed' && (
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={closeForm.processing}
                                onClick={() =>
                                    closeForm.post(close.url(ticket.id))
                                }
                            >
                                Close ticket
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            <div className="max-w-3xl space-y-4">
                {replies.map((item) => (
                    <div
                        key={item.id}
                        className={`rounded-xl border p-5 ${
                            item.user_type === 'staff'
                                ? 'bg-muted/40'
                                : 'bg-background'
                        }`}
                    >
                        <div className="mb-2 flex items-center justify-between gap-3">
                            <p className="text-sm font-medium">
                                {item.user_type === 'staff'
                                    ? 'Support team'
                                    : 'You'}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {new Date(item.created_at).toLocaleString()}
                            </p>
                        </div>
                        <p className="text-sm whitespace-pre-wrap">
                            {item.message}
                        </p>
                        <TicketAttachmentList attachments={item.attachments} />
                    </div>
                ))}

                {ticket.status !== 'closed' && (
                    <form
                        onSubmit={submit}
                        className="space-y-4 rounded-xl border p-5"
                    >
                        <div className="space-y-2">
                            <Label htmlFor="reply">Reply</Label>
                            <textarea
                                id="reply"
                                required
                                rows={5}
                                value={form.data.message}
                                onChange={(e) =>
                                    form.setData('message', e.target.value)
                                }
                                placeholder="Type your reply…"
                                className="bg-background min-h-28 w-full rounded-md border p-3"
                            />
                            <InputError message={form.errors.message} />
                        </div>
                        <TicketAttachmentInput
                            id="reply-attachments"
                            files={form.data.attachments}
                            onChange={(files) =>
                                form.setData('attachments', files)
                            }
                            error={form.errors.attachments}
                            progress={form.progress?.percentage}
                        />
                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                Send reply
                            </Button>
                        </div>
                    </form>
                )}

                {ticket.status === 'closed' && (
                    <p className="text-muted-foreground rounded-xl border border-dashed p-5 text-center text-sm">
                        This ticket is closed. Open a new ticket if you need
                        further assistance.
                    </p>
                )}
            </div>
        </>
    );
}
