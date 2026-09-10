import { Head, Link } from '@inertiajs/react';
import { create, show } from '@/routes/client/tickets';

type Ticket = {
    id: number;
    ticket_number: string;
    subject: string;
    status: string;
    priority: string;
    created_at: string;
    department: { name: string };
};

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

export default function TicketsIndex({ tickets }: { tickets: Ticket[] }) {
    return (
        <>
            <Head title="Support tickets" />
            <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">Support tickets</h1>
                    <p className="text-muted-foreground mt-2">
                        View and manage your support requests.
                    </p>
                </div>
                <Link
                    href={create()}
                    className="bg-primary text-primary-foreground rounded-lg px-4 py-2 text-sm"
                >
                    Open new ticket
                </Link>
            </div>

            {tickets.length === 0 && (
                <div className="rounded-xl border border-dashed p-12 text-center">
                    <h2 className="font-semibold">No tickets yet</h2>
                    <p className="text-muted-foreground mt-2">
                        Need help? Open a support ticket and we'll get back to
                        you.
                    </p>
                    <Link
                        href={create()}
                        className="bg-primary text-primary-foreground mt-5 inline-block rounded-lg px-4 py-2 text-sm"
                    >
                        Open your first ticket
                    </Link>
                </div>
            )}

            {tickets.length > 0 && (
                <div className="divide-y rounded-xl border">
                    {tickets.map((ticket) => (
                        <Link
                            key={ticket.id}
                            href={show(ticket.id)}
                            className="hover:bg-muted flex flex-wrap items-center justify-between gap-3 p-5"
                        >
                            <div className="min-w-0">
                                <p className="font-medium break-words">
                                    {ticket.subject}
                                </p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {ticket.ticket_number} ·{' '}
                                    {ticket.department.name} ·{' '}
                                    {new Date(
                                        ticket.created_at,
                                    ).toLocaleDateString()}
                                </p>
                            </div>
                            <div className="flex gap-2">
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
                        </Link>
                    ))}
                </div>
            )}
        </>
    );
}