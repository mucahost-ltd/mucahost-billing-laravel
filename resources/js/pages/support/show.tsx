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
import { assign, reply, status } from '@/routes/support';

type Ticket = {
    id: number;
    ticket_number: string;
    subject: string;
    status: string;
    priority: string;
    created_at: string;
    client: { first_name: string; last_name: string; email: string };
    department: { name: string };
    service: { domain: string | null } | null;
    assignedStaff: { id: number; name: string } | null;
};

type Reply = {
    id: number;
    user_type: 'client' | 'staff';
    user_id: number;
    message: string;
    created_at: string;
    attachments: TicketAttachment[];
};

type Staff = { id: number; name: string };

const statusStyles: Record<string, string> = {
    open: 'bg-blue-100 text-blue-700',
    answered: 'bg-green-100 text-green-700',
    'customer-reply': 'bg-amber-100 text-amber-700',
    closed: 'bg-gray-100 text-gray-600',
};

const priorityStyles: Record<string, string> = {
    low: 'bg-gray-100 text-gray-600',
    medium: 'bg-amber-100 text-amber-700',
    high: 'bg-red-100 text-red-700',
};

export default function SupportShow({
    ticket,
    replies,
    staff,
}: {
    ticket: Ticket;
    replies: Reply[];
    staff: Staff[];
}) {
    const replyForm = useForm<{ message: string; attachments: File[] }>({
        message: '',
        attachments: [],
    });
    const statusForm = useForm({ status: ticket.status });
    const assignForm = useForm({
        assigned_to: ticket.assignedStaff?.id
            ? String(ticket.assignedStaff.id)
            : '',
    });

    function submitReply(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        replyForm.post(reply.url(ticket.id), {
            forceFormData: true,
            onSuccess: () => replyForm.reset(),
        });
    }

    return (
        <>
            <Head title={ticket.subject} />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
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
                        <p className="text-muted-foreground mt-1 text-sm">
                            {ticket.client.first_name} {ticket.client.last_name}{' '}
                            · {ticket.client.email}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span
                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${statusStyles[ticket.status] ?? 'bg-gray-100 text-gray-600'}`}
                        >
                            {ticket.status.replace('-', ' ')}
                        </span>
                        <span
                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${priorityStyles[ticket.priority] ?? 'bg-gray-100 text-gray-600'}`}
                        >
                            {ticket.priority}
                        </span>
                    </div>
                </header>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="space-y-6 md:col-span-2">
                        <div className="space-y-4">
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
                                                : ticket.client.first_name}{' '}
                                            {item.user_type === 'client'
                                                ? ticket.client.last_name
                                                : ''}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {new Date(
                                                item.created_at,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                    <p className="text-sm whitespace-pre-wrap">
                                        {item.message}
                                    </p>
                                    <TicketAttachmentList
                                        attachments={item.attachments}
                                    />
                                </div>
                            ))}
                        </div>

                        {ticket.status !== 'closed' && (
                            <form
                                onSubmit={submitReply}
                                className="space-y-4 rounded-xl border p-5"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="reply">
                                        Reply to client
                                    </Label>
                                    <textarea
                                        id="reply"
                                        required
                                        rows={5}
                                        value={replyForm.data.message}
                                        onChange={(e) =>
                                            replyForm.setData(
                                                'message',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Type your reply…"
                                        className="bg-background min-h-28 w-full rounded-md border p-3"
                                    />
                                    <InputError
                                        message={replyForm.errors.message}
                                    />
                                </div>
                                <TicketAttachmentInput
                                    id="reply-attachments"
                                    files={replyForm.data.attachments}
                                    onChange={(files) =>
                                        replyForm.setData('attachments', files)
                                    }
                                    error={replyForm.errors.attachments}
                                    progress={replyForm.progress?.percentage}
                                />
                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        disabled={replyForm.processing}
                                    >
                                        Send reply
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>

                    <div className="space-y-6">
                        <div className="space-y-4 rounded-xl border p-5">
                            <h2 className="font-semibold">Status</h2>
                            <select
                                value={statusForm.data.status}
                                onChange={(e) =>
                                    statusForm.setData('status', e.target.value)
                                }
                                className="bg-background w-full rounded-md border p-2 text-sm"
                            >
                                <option value="open">Open</option>
                                <option value="answered">Answered</option>
                                <option value="customer-reply">
                                    Customer reply
                                </option>
                                <option value="closed">Closed</option>
                            </select>
                            <Button
                                size="sm"
                                disabled={statusForm.processing}
                                onClick={() =>
                                    statusForm.patch(status.url(ticket.id))
                                }
                            >
                                Update status
                            </Button>
                        </div>

                        <div className="space-y-4 rounded-xl border p-5">
                            <h2 className="font-semibold">Assigned staff</h2>
                            <select
                                value={assignForm.data.assigned_to}
                                onChange={(e) =>
                                    assignForm.setData(
                                        'assigned_to',
                                        e.target.value,
                                    )
                                }
                                className="bg-background w-full rounded-md border p-2 text-sm"
                            >
                                <option value="">Unassigned</option>
                                {staff.map((member) => (
                                    <option key={member.id} value={member.id}>
                                        {member.name}
                                    </option>
                                ))}
                            </select>
                            <Button
                                size="sm"
                                disabled={assignForm.processing}
                                onClick={() =>
                                    assignForm.patch(assign.url(ticket.id))
                                }
                            >
                                Assign
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
