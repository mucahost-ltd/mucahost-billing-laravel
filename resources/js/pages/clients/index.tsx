import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownUp,
    ChevronRight,
    Clock3,
    List,
    Plus,
    RefreshCw,
    Search,
    Users,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ClientDateRangePicker from '@/components/client-date-range-picker';
import { create as clientCreate, index as clientsIndex, show as clientShow } from '@/routes/clients';

type ClientStatus = 'active' | 'inactive' | 'closed';
type Client = {
    id: number; name: string; initials: string; email: string; phone: string | null;
    company_name: string | null; country: string | null; status: ClientStatus;
    credit_balance: string; currency: { code: string; symbol: string } | null;
    services_count: number; invoices_count: number; tickets_count: number; created_at: string;
};
type Filters = { search?: string | null; status?: string | null; country?: string | null; sort?: string; direction?: string };
type ClientsProps = {
    clients: { data: Client[]; total: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null };
    filters: Filters;
    statusCounts: Record<'all' | ClientStatus, number>;
    countries: string[];
    dateRange: { start: string; end: string };
    overview: { new_clients: number; monthly: Array<{ label: string; value: number }> };
};

const statusStyles: Record<ClientStatus, string> = {
    active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    inactive: 'border-slate-200 bg-slate-50 text-slate-600',
    closed: 'border-slate-200 bg-slate-50 text-slate-500',
};

export default function Clients({ clients, filters, statusCounts, dateRange, overview }: ClientsProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [view, setView] = useState<'overview' | 'list'>('list');
    const quickFilters = [
        { label: 'All clients', value: '', count: statusCounts.all },
        { label: 'Active clients', value: 'active', count: statusCounts.active },
        { label: 'Inactive clients', value: 'inactive', count: statusCounts.inactive },
        { label: 'Closed clients', value: 'closed', count: statusCounts.closed },
    ];

    function applyFilters(next: Partial<Filters>) {
        router.get(clientsIndex(), { ...filters, ...next, page: undefined }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        applyFilters({ search: search || null });
    }

    return (
        <>
            <Head title="Clients" />
            <div className="mx-auto w-full max-w-[1400px] space-y-6 p-5 lg:p-8">
                <header className="flex items-center justify-between gap-4">
                    <h1 className="text-3xl font-light tracking-tight">Clients</h1>
                    <Button asChild><Link href={clientCreate()}><Plus /> Add client</Link></Button>
                </header>

                <nav className="flex gap-8 border-b" aria-label="Client views">
                    {(['overview', 'list'] as const).map((item) => (
                        <button key={item} type="button" onClick={() => setView(item)}
                            className={`border-b-2 px-2 pb-3 text-sm font-medium capitalize ${view === item ? 'border-slate-900 text-foreground dark:border-slate-100' : 'border-transparent text-muted-foreground hover:text-foreground'}`}>
                            {item === 'list' ? 'Clients list' : 'Overview'}
                        </button>
                    ))}
                </nav>

                {view === 'overview' ? (
                    <Overview clients={clients} statusCounts={statusCounts} dateRange={dateRange} overview={overview} onViewList={() => setView('list')} />
                ) : (
                    <div className="grid gap-6 lg:grid-cols-[250px_minmax(0,1fr)]">
                        <aside className="space-y-6">
                            <div>
                                <p className="rounded-md bg-muted/55 px-3 py-2 text-sm text-muted-foreground">Quick filters</p>
                                <div className="mt-3 flex gap-1 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible">
                                    {quickFilters.map((filter) => {
                                        const active = (filters.status ?? '') === filter.value;
                                        return <button key={filter.label} type="button" onClick={() => applyFilters({ status: filter.value || null })}
                                            className={`flex shrink-0 items-center justify-between gap-4 rounded-md px-3 py-2 text-left text-sm transition-colors ${active ? 'bg-[#1e5608] text-white' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}>
                                            <span>{filter.label}</span><span className="text-xs opacity-70">{filter.count}</span>
                                        </button>;
                                    })}
                                </div>
                            </div>
                            <div>
                                <div className="flex items-center justify-between rounded-md bg-muted/55 px-3 py-2 text-sm text-muted-foreground"><span>Client segments</span><button type="button" className="underline">Add</button></div>
                                <Input className="mt-3" placeholder="Quick search..." />
                                <p className="mt-4 px-3 text-sm text-muted-foreground">Default <span className="float-right">⚙</span></p>
                            </div>
                        </aside>

                        <section className="min-w-0 space-y-4">
                            <form onSubmit={submitSearch} className="relative">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input aria-label="Search clients" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Quick search by name, email or company..." className="h-11 border-blue-400 pl-10 shadow-[0_0_0_2px_rgba(96,165,250,0.15)]" />
                            </form>
                            <div className="flex flex-wrap items-center gap-2">
                                <Input className="min-w-48 flex-1" placeholder="Add filter" />
                                <span className="text-sm text-muted-foreground">Sort by</span>
                                <select aria-label="Sort clients" value={filters.sort ?? 'created_at'} onChange={(event) => applyFilters({ sort: event.target.value })} className="h-9 rounded-md border bg-background px-3 text-sm">
                                    <option value="created_at">Date created</option><option value="name">Name</option><option value="services">Services</option><option value="invoices">Invoices</option>
                                </select>
                                <Button type="button" variant="outline" size="icon" aria-label="Reverse sort order" onClick={() => applyFilters({ direction: filters.direction === 'asc' ? 'desc' : 'asc' })}><ArrowDownUp /></Button>
                                <Button type="button" variant="outline" size="icon" aria-label="List view"><List /></Button>
                                <Button type="button" variant="outline" size="icon" aria-label="Refresh clients" onClick={() => applyFilters({})}><RefreshCw /></Button>
                            </div>
                            <div className="overflow-hidden rounded-xl border bg-background">
                                {clients.data.map((client) => <ClientRow key={client.id} client={client} />)}
                                {clients.data.length === 0 && <div className="p-12 text-center"><Users className="mx-auto size-8 text-muted-foreground" /><p className="mt-3 font-medium">No clients found</p></div>}
                            </div>
                            <div className="flex items-center justify-between text-sm text-muted-foreground">
                                <span>{clients.total === 0 ? 'No clients' : `${clients.from}–${clients.to} of ${clients.total}`}</span>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" asChild disabled={!clients.prev_page_url}>{clients.prev_page_url ? <Link href={clients.prev_page_url}>Previous</Link> : <span>Previous</span>}</Button>
                                    <Button variant="outline" size="sm" asChild disabled={!clients.next_page_url}>{clients.next_page_url ? <Link href={clients.next_page_url}>Next</Link> : <span>Next</span>}</Button>
                                </div>
                            </div>
                        </section>
                    </div>
                )}
            </div>
        </>
    );
}

function ClientRow({ client }: { client: Client }) {
    return <Link href={clientShow(client.id)} className="group grid gap-3 border-b px-4 py-4 transition-colors last:border-b-0 hover:bg-muted/25 md:grid-cols-[minmax(260px,1fr)_110px_150px_24px] md:items-center">
        <div className="flex min-w-0 items-center gap-3">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-slate-800 text-sm font-semibold text-white">{client.initials}</div>
            <div className="min-w-0"><p className="truncate font-medium">{client.name}</p><p className="mt-0.5 truncate text-sm text-muted-foreground underline underline-offset-2">{client.email}</p><p className="mt-0.5 text-xs text-muted-foreground">{client.created_at ? new Intl.DateTimeFormat('en', { day: 'numeric', month: 'short' }).format(new Date(`${client.created_at}T00:00:00`)) : '—'}</p></div>
        </div>
        <Badge variant="outline" className={`w-fit capitalize ${statusStyles[client.status]}`}>{client.status}</Badge>
        <div className="text-xs text-muted-foreground"><span>{client.services_count} services</span><span className="mx-2">·</span><span>{client.invoices_count} invoices</span></div>
        <ChevronRight className="hidden size-4 text-muted-foreground group-hover:text-foreground md:block" />
    </Link>;
}

function Overview({ clients, statusCounts, dateRange, overview, onViewList }: { clients: ClientsProps['clients']; statusCounts: ClientsProps['statusCounts']; dateRange: ClientsProps['dateRange']; overview: ClientsProps['overview']; onViewList: () => void }) {
    const recent = clients.data.slice(0, 5);
    function applyRange(range: ClientsProps['dateRange']) {
        router.get(clientsIndex(), range, { preserveState: true, preserveScroll: true, replace: true });
    }
    const max = Math.max(...overview.monthly.map((point) => point.value), 1);
    return <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
        <section className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Client stats</h2><ClientDateRangePicker value={dateRange} onApply={applyRange} /></div>
            <div className="grid gap-3 sm:grid-cols-2">
                <Metric label="Active clients" value={statusCounts.active} />
                <Metric label="New clients" value={overview.new_clients} />
            </div>
            <div className="rounded-xl border bg-muted/10 p-5"><div className="flex items-center justify-between"><h2 className="font-semibold">Clients</h2><Clock3 className="size-4 text-muted-foreground" /></div><div className="mt-8 flex h-48 items-end gap-3 border-b border-l px-4">{overview.monthly.map((point) => <div key={point.label} title={`${point.label}: ${point.value}`} className="flex-1 rounded-t bg-blue-400/80 transition-colors hover:bg-blue-600" style={{ height: `${Math.max((point.value / max) * 100, point.value ? 4 : 0)}%` }} />)}</div><div className="mt-2 flex justify-between text-xs text-muted-foreground"><span>{overview.monthly[0]?.label}</span><span>{overview.monthly[5]?.label}</span><span>{overview.monthly[11]?.label}</span></div></div>
        </section>
        <section className="space-y-4"><div className="flex items-center justify-between"><h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Recent clients</h2><button type="button" onClick={onViewList} className="text-sm text-blue-600 hover:underline">View all</button></div><Input placeholder="Quick search by name, email or company..." /><div className="overflow-hidden rounded-xl border bg-background">{recent.map((client) => <ClientRow key={client.id} client={client} />)}</div></section>
    </div>;
}

function Metric({ label, value }: { label: string; value: number }) {
    return <div className="rounded-xl bg-muted/50 p-5"><p className="text-sm text-muted-foreground">{label}</p><p className="mt-2 text-3xl font-light">{value}</p><p className="mt-2 text-xs text-blue-600">◷ Last year</p></div>;
}
