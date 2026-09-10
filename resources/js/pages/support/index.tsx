import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/support';
import { destroy, store, update } from '@/routes/support-departments';

type Ticket = {
    id: number;
    ticket_number: string;
    subject: string;
    status: string;
    priority: string;
    created_at: string;
    client: { first_name: string; last_name: string; email: string };
    department: { name: string };
};

type Department = { id: number; name: string; email: string; tickets_count: number };

type Pagination = {
    data: Ticket[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
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

function DepartmentForm({ department, done }: { department: Department | null; done: () => void }) {
    const form = useForm({
        name: department?.name ?? '',
        email: department?.email ?? '',
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                if (department) {
                    form.patch(update.url(department.id), { onSuccess: done });
                } else {
                    form.post(store.url(), { onSuccess: done });
                }
            }}
            className="space-y-4"
        >
            <div className="space-y-2">
                <Label htmlFor="department-name">Department name</Label>
                <Input
                    id="department-name"
                    required
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
                <InputError message={form.errors.name} />
            </div>
            <div className="space-y-2">
                <Label htmlFor="department-email">Email</Label>
                <Input
                    id="department-email"
                    type="email"
                    required
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
                <InputError message={form.errors.email} />
            </div>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={done}>
                    Cancel
                </Button>
                <Button disabled={form.processing}>
                    {department ? 'Save changes' : 'Create department'}
                </Button>
            </div>
        </form>
    );
}

export default function SupportIndex({
    tickets,
    departments,
    filters,
}: {
    tickets: Pagination;
    departments: Department[];
    filters: { status?: string; department?: string; priority?: string; search?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<Department | null | undefined>(undefined);
    const [deleting, setDeleting] = useState<Department | null>(null);
    const deletion = useForm({});

    function applyFilter(key: string, value: string) {
        router.get(index(), { ...filters, [key]: value || undefined }, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Support tickets" />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Support tickets</h1>
                        <p className="text-muted-foreground mt-2">
                            Manage client support requests.
                        </p>
                    </div>
                    <Button variant="outline" onClick={() => setEditing(null)}>
                        Create department
                    </Button>
                </header>

                <div className="rounded-xl border">
                    <div className="flex flex-wrap items-center justify-between gap-3 bg-muted/40 px-5 py-4">
                        <h2 className="font-semibold">Departments</h2>
                        <div className="flex flex-wrap gap-2">
                            {departments.map((department) => (
                                <div
                                    key={department.id}
                                    className="flex items-center gap-2 rounded-lg border bg-background px-3 py-1.5 text-sm"
                                >
                                    <span>{department.name}</span>
                                    <span className="text-muted-foreground text-xs">
                                        {department.tickets_count}
                                    </span>
                                    <button
                                        type="button"
                                        className="text-muted-foreground hover:text-foreground text-xs"
                                        onClick={() => setEditing(department)}
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        className="text-muted-foreground hover:text-destructive text-xs"
                                        onClick={() => setDeleting(department)}
                                    >
                                        Delete
                                    </button>
                                </div>
                            ))}
                            {departments.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No departments yet.
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        aria-label="Search tickets"
                        placeholder="Search tickets…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                applyFilter('search', search);
                            }
                        }}
                        className="max-w-xs"
                    />
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) => applyFilter('status', e.target.value)}
                        className="rounded-md border bg-background p-2 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option value="open">Open</option>
                        <option value="answered">Answered</option>
                        <option value="customer-reply">Customer reply</option>
                        <option value="closed">Closed</option>
                    </select>
                    <select
                        value={filters.department ?? ''}
                        onChange={(e) => applyFilter('department', e.target.value)}
                        className="rounded-md border bg-background p-2 text-sm"
                    >
                        <option value="">All departments</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </select>
                    <select
                        value={filters.priority ?? ''}
                        onChange={(e) => applyFilter('priority', e.target.value)}
                        className="rounded-md border bg-background p-2 text-sm"
                    >
                        <option value="">All priorities</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>

                {tickets.data.length === 0 && (
                    <div className="rounded-xl border border-dashed p-12 text-center">
                        <h2 className="font-semibold">No tickets found</h2>
                        <p className="text-muted-foreground mt-2">
                            Try adjusting your filters.
                        </p>
                    </div>
                )}

                {tickets.data.length > 0 && (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/40 text-muted-foreground">
                                <tr>
                                    {['Ticket', 'Client', 'Department', 'Status', 'Priority', 'Created'].map((label) => (
                                        <th key={label} className="px-5 py-3 font-medium">
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {tickets.data.map((ticket) => (
                                    <tr key={ticket.id} className="hover:bg-muted/40">
                                        <td className="px-5 py-4">
                                            <Link
                                                href={show(ticket.id)}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {ticket.subject}
                                            </Link>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {ticket.ticket_number}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4">
                                            {ticket.client.first_name}{' '}
                                            {ticket.client.last_name}
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {ticket.client.email}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4">
                                            {ticket.department.name}
                                        </td>
                                        <td className="px-5 py-4">
                                            <span
                                                className={`rounded-full px-2.5 py-1 text-xs font-medium ${statusStyles[ticket.status] ?? 'bg-gray-100 text-gray-600'}`}
                                            >
                                                {ticket.status.replace('-', ' ')}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span
                                                className={`rounded-full px-2.5 py-1 text-xs font-medium ${priorityStyles[ticket.priority] ?? 'bg-gray-100 text-gray-600'}`}
                                            >
                                                {ticket.priority}
                                            </span>
                                        </td>
                                        <td className="text-muted-foreground px-5 py-4">
                                            {new Date(
                                                ticket.created_at,
                                            ).toLocaleDateString()}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Dialog open={editing !== undefined} onOpenChange={(open) => { if (!open) { setEditing(undefined); } }}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {editing ? 'Edit department' : 'Create department'}
                            </DialogTitle>
                        </DialogHeader>
                        {editing !== undefined && (
                            <DepartmentForm
                                key={editing?.id ?? 'new'}
                                department={editing}
                                done={() => setEditing(undefined)}
                            />
                        )}
                    </DialogContent>
                </Dialog>

                <Dialog open={deleting !== null} onOpenChange={(open) => { if (!open) { setDeleting(null); } }}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete {deleting?.name}?</DialogTitle>
                        </DialogHeader>
                        <p className="text-sm text-muted-foreground">
                            Only departments without tickets can be deleted.
                        </p>
                        <InputError message={(deletion.errors as Record<string, string>).department} />
                        <Button
                            variant="destructive"
                            disabled={deletion.processing}
                            onClick={() => {
                                if (deleting) {
                                    deletion.delete(destroy.url(deleting.id), { onSuccess: () => setDeleting(null) });
                                }
                            }}
                        >
                            Delete department
                        </Button>
                    </DialogContent>
                </Dialog>

                {tickets.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-muted-foreground text-sm">
                            Page {tickets.current_page} of {tickets.last_page} ·{' '}
                            {tickets.total} tickets
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={tickets.current_page <= 1}
                                onClick={() =>
                                    router.get(
                                        index(),
                                        { ...filters, page: tickets.current_page - 1 },
                                        { preserveState: true },
                                    )
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={tickets.current_page >= tickets.last_page}
                                onClick={() =>
                                    router.get(
                                        index(),
                                        { ...filters, page: tickets.current_page + 1 },
                                        { preserveState: true },
                                    )
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}