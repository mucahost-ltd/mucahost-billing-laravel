import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2, CalendarDays, Edit3, Mail, MapPin, Phone, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import ClientDateRangePicker from '@/components/client-date-range-picker';
import { index as clientsIndex, edit as clientEdit, status as clientStatus } from '@/routes/clients';

type Client = {
    id: number; name: string; initials: string; email: string; phone: string | null;
    company_name: string | null; address: string[]; status: 'active' | 'inactive' | 'closed';
    credit_balance: string; currency: { code: string; symbol: string } | null;
    email_verified_at: string | null; created_at: string; admin_notes: string | null;
};
type Service = { id: number; product: string; domain: string | null; status: string; billing_cycle: string; next_due_date: string | null };
type Invoice = { id: number; invoice_number: string; status: string; total: string; currency: string; due_date: string };
type Transaction = { id: number; invoice_number: string | null; type: string; gateway: string; amount: string; currency: string; created_at: string };
type Ticket = { id: number; ticket_number: string; subject: string; status: string; priority: string; updated_at: string };
type Tab = 'overview' | 'services' | 'invoices' | 'transactions' | 'tickets';
type Note = { id: number; body: string; author: string; created_at: string };
type ChartPoint = { label: string; value: number };
type Props = {
    client: Client;
    summary: { invoiced: number; invoices: number; unpaid: number; revenue: number; orders: number; outstanding: number; tickets: number; active_tickets: number; services: number; transactions: number };
    charts: { invoiced: ChartPoint[]; invoices: ChartPoint[] };
    dateRange: { start: string; end: string };
    notes: Note[];
    services: Service[]; invoices: Invoice[]; transactions: Transaction[]; tickets: Ticket[];
};

const statusTone: Record<Client['status'], string> = {
    active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    inactive: 'border-amber-200 bg-amber-50 text-amber-700',
    closed: 'border-slate-200 bg-slate-50 text-slate-600',
};

function dateLabel(value: string | null) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value.slice(0, 10)}T00:00:00`));
}

export default function ClientShow({ client, summary, charts, dateRange, notes, services, invoices, transactions, tickets }: Props) {
    const [tab, setTab] = useState<Tab>('overview');
    const statusForm = useForm({ status: client.status });
    const tabs: Array<{ key: Tab; label: string; count?: number }> = [
        { key: 'overview', label: 'Overview' },
        { key: 'services', label: 'Services', count: summary.services },
        { key: 'invoices', label: 'Invoices', count: summary.invoices },
        { key: 'transactions', label: 'Transactions', count: summary.transactions },
        { key: 'tickets', label: 'Tickets', count: summary.tickets },
    ];

    return <><Head title={client.name} /><div className="mx-auto w-full max-w-[1400px] space-y-6 p-5 lg:p-8">
        <Button variant="ghost" size="sm" asChild className="text-muted-foreground"><Link href={clientsIndex()}><ArrowLeft /> Back to clients</Link></Button>
        <div className="grid gap-6 lg:grid-cols-[250px_minmax(0,1fr)]">
            <aside className="space-y-4">
                <div className="rounded-xl border bg-background p-5">
                    <div className="relative mx-auto flex size-24 items-center justify-center rounded-full bg-slate-800 text-2xl font-semibold text-white ring-4 ring-muted">{client.initials}<span className="absolute right-1 bottom-1 size-3 rounded-full border-2 border-background bg-emerald-500" /></div>
                    <h1 className="mt-4 text-center text-lg font-semibold">{client.name}</h1><p className="mt-1 break-all text-center text-sm text-muted-foreground">{client.email}</p>
                    <div className="mt-4 flex justify-center"><Badge variant="outline" className={`capitalize ${statusTone[client.status]}`}>{client.status}</Badge></div>
                    <div className="mt-5 space-y-3 border-t pt-4 text-xs text-muted-foreground"><p>Client since <strong className="float-right font-medium text-foreground">{dateLabel(client.created_at)}</strong></p><p>Client ID <strong className="float-right font-medium text-foreground">#{client.id}</strong></p><p>Phone <strong className="float-right max-w-32 truncate font-medium text-foreground">{client.phone ?? '—'}</strong></p><p>Company <strong className="float-right max-w-32 truncate font-medium text-foreground">{client.company_name ?? '—'}</strong></p><p>Country <strong className="float-right font-medium text-foreground">{client.country ?? '—'}</strong></p></div>
                </div>
                <nav className="rounded-xl border bg-background p-2" aria-label="Client sections">{tabs.map((item) => <button key={item.key} type="button" onClick={() => setTab(item.key)} className={`flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm ${tab === item.key ? 'bg-[#1e5608] text-white' : 'text-muted-foreground hover:bg-muted'}`}><span>{item.label}</span>{item.count !== undefined && <span className="text-xs opacity-70">{item.count}</span>}</button>)}</nav>
            </aside>
            <main className="min-w-0 space-y-6">
                <header className="flex flex-wrap items-center justify-between gap-4 border-b pb-5"><div><p className="text-sm text-muted-foreground">Client workspace</p><h2 className="mt-1 text-3xl font-light tracking-tight">Client overview</h2></div><div className="flex items-center gap-2"><Button variant="outline" asChild><Link href={clientEdit(client.id)}><Edit3 /> Edit profile</Link></Button><select aria-label="Change client status" value={statusForm.data.status} onChange={(event) => { const status = event.target.value as Client['status']; statusForm.setData('status', status); statusForm.patch(clientStatus.url(client.id)); }} className="border-input bg-background h-9 rounded-md border px-3 text-sm capitalize"><option value="active">Active</option><option value="inactive">Inactive</option><option value="closed">Closed</option></select></div></header>
                {tab === 'overview' && <Overview client={client} summary={summary} charts={charts} dateRange={dateRange} notes={notes} setTab={setTab} />}
                {tab === 'services' && <DataTable headers={['Product', 'Domain', 'Status', 'Cycle', 'Next due']} rows={services.map((item) => [item.product, item.domain ?? '—', item.status, item.billing_cycle.replaceAll('_', ' '), dateLabel(item.next_due_date)])} empty="Services" />}
                {tab === 'invoices' && <DataTable headers={['Invoice', 'Status', 'Amount', 'Due date']} rows={invoices.map((item) => [item.invoice_number, item.status, `${item.currency} ${Number(item.total).toLocaleString()}`, dateLabel(item.due_date)])} empty="Invoices" />}
                {tab === 'transactions' && <DataTable headers={['Invoice', 'Type', 'Gateway', 'Amount', 'Date']} rows={transactions.map((item) => [item.invoice_number ?? '—', item.type, item.gateway, `${item.currency} ${Number(item.amount).toLocaleString()}`, dateLabel(item.created_at)])} empty="Transactions" />}
                {tab === 'tickets' && <DataTable headers={['Ticket', 'Subject', 'Status', 'Priority', 'Updated']} rows={tickets.map((item) => [item.ticket_number, item.subject, item.status, item.priority, dateLabel(item.updated_at)])} empty="Tickets" />}
            </main>
        </div>
    </div></>;
}

function Overview({ client, summary, charts, dateRange, notes, setTab }: { client: Client; summary: Props['summary']; charts: Props['charts']; dateRange: Props['dateRange']; notes: Note[]; setTab: (tab: Tab) => void }) {
    const noteForm = useForm({ body: '' });
    function applyRange(range: Props['dateRange']) {
        router.get(`/admin/clients/${client.id}`, range, { preserveState: true, preserveScroll: true, replace: true });
    }
    return <div className="space-y-7">
        <section><div className="mb-3 flex flex-wrap items-center justify-between gap-3"><p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Client stats · All currencies</p><ClientDateRangePicker value={dateRange} onApply={applyRange} /></div><div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{[
            ['Total invoiced', summary.invoiced, true], ['Total invoices', summary.invoices, false], ['Unpaid invoices', summary.unpaid, true], ['Revenue collected', summary.revenue, true], ['Total orders', summary.orders, false], ['Total outstanding', summary.outstanding, true], ['Total tickets', summary.tickets, false], ['Active tickets', summary.active_tickets, false],
        ].map(([label, value, money]) => <button key={String(label)} type="button" onClick={() => typeof label === 'string' && ['Total invoices', 'Unpaid invoices'].includes(label) ? setTab('invoices') : undefined} className="rounded-xl bg-muted/50 p-4 text-left transition-colors hover:bg-muted"><p className="text-xs text-muted-foreground">{label}</p><p className="mt-2 text-2xl font-light">{money ? `${client.currency?.symbol ?? ''} ${Number(value).toLocaleString()}` : value}</p><p className="mt-2 text-xs text-blue-600">◷ {dateRange.start} – {dateRange.end}</p></button>)}</div></section>
        <section className="grid gap-6 xl:grid-cols-2"><Chart title="Total invoiced" points={charts.invoiced} currency={client.currency?.symbol} /><Chart title="Invoices" points={charts.invoices} /></section>
        <section><h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Quick actions</h3><div className="flex flex-wrap gap-2"><Button variant="outline" asChild><a href="/admin/billing">Place new order</a></Button><Button variant="outline" asChild><a href="/admin/support">Open new ticket</a></Button><Button variant="outline" asChild><a href={`mailto:${client.email}`}>Send email</a></Button><Button variant="outline" asChild><a href={`/admin/clients/${client.id}/edit`}>Edit account</a></Button></div></section>
        <section className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]"><div className="rounded-xl border p-5"><h3 className="font-semibold">Account notes</h3><p className="mt-1 text-sm text-muted-foreground">Internal notes visible only to your team.</p><form onSubmit={(event) => { event.preventDefault(); noteForm.post(`/admin/clients/${client.id}/notes`, { onSuccess: () => noteForm.reset() }); }} className="mt-4 flex gap-2"><textarea value={noteForm.data.body} onChange={(event) => noteForm.setData('body', event.target.value)} placeholder="Enter a new note here..." className="min-h-20 flex-1 rounded-md border bg-background p-3 text-sm" /><Button type="submit" disabled={noteForm.processing}>Add</Button></form><div className="mt-4 space-y-3">{notes.map((note) => <article key={note.id} className="rounded-md border bg-muted/20 p-3 text-sm"><p className="whitespace-pre-wrap">{note.body}</p><p className="mt-2 text-xs text-muted-foreground">{note.author} · {dateLabel(note.created_at)}</p><button type="button" onClick={() => router.delete(`/admin/clients/${client.id}/notes/${note.id}`)} className="mt-2 text-xs text-red-600 hover:underline">Delete</button></article>)}{notes.length === 0 && <p className="text-sm text-muted-foreground">No notes added yet.</p>}</div></div><div className="rounded-xl border bg-background p-5"><h3 className="font-semibold">Profile</h3><div className="mt-5 space-y-4 text-sm text-muted-foreground"><p className="flex gap-3"><Mail className="size-4 shrink-0" /><span className="break-all">{client.email}</span></p><p className="flex gap-3"><Phone className="size-4 shrink-0" />{client.phone ?? 'No phone'}</p><p className="flex gap-3"><Building2 className="size-4 shrink-0" />{client.company_name ?? 'No company'}</p><p className="flex gap-3"><MapPin className="size-4 shrink-0" />{client.address.length ? client.address.join(', ') : 'No address'}</p><p className="flex gap-3"><ShieldCheck className="size-4 shrink-0" />{client.email_verified_at ? 'Email verified' : 'Email not verified'}</p><p className="flex gap-3"><CalendarDays className="size-4 shrink-0" />Credit: {client.currency?.code ?? '—'} {Number(client.credit_balance).toLocaleString()}</p></div></div></section>
    </div>;
}

function Chart({ title, points, currency }: { title: string; points: ChartPoint[]; currency?: string }) {
    const max = Math.max(...points.map((point) => point.value), 1);
    return <div className="rounded-xl border p-5"><div className="flex items-center justify-between"><h3 className="text-sm font-semibold">{title}</h3><span className="text-xs text-muted-foreground">All {currency ?? ''}</span></div><div className="mt-7 flex h-44 items-end gap-2 border-b border-l px-3">{points.map((point) => <div key={point.label} className="group relative flex h-full flex-1 items-end"><span className="absolute bottom-full left-1/2 hidden -translate-x-1/2 rounded bg-slate-900 px-2 py-1 text-xs text-white group-hover:block">{currency ?? ''} {point.value.toLocaleString()}</span><div className="w-full rounded-t bg-blue-500 transition-all hover:bg-blue-600" style={{ height: `${Math.max((point.value / max) * 100, point.value ? 4 : 0)}%` }} /></div>)}</div><div className="mt-2 flex justify-between text-xs text-muted-foreground">{points.filter((_, index) => index % 2 === 0).map((point) => <span key={point.label}>{point.label}</span>)}</div></div>;
}

function DataTable({ headers, rows, empty }: { headers: string[]; rows: string[][]; empty: string }) {
    return <section className="overflow-hidden rounded-xl border bg-background"><div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="border-b bg-muted/35 text-muted-foreground"><tr>{headers.map((header) => <th key={header} className="px-5 py-3 font-medium">{header}</th>)}</tr></thead><tbody className="divide-y">{rows.map((row, index) => <tr key={index}>{row.map((value, cell) => <td key={`${index}-${headers[cell]}`} className={`px-5 py-4 ${cell === 0 ? 'font-medium' : 'capitalize text-muted-foreground'}`}>{value}</td>)}</tr>)}</tbody></table></div>{rows.length === 0 && <p className="p-10 text-center text-sm text-muted-foreground">No {empty.toLowerCase()} found for this client.</p>}</section>;
}
