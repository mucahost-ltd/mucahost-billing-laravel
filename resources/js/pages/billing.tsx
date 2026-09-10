import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowDownUp,
    CalendarDays,
    CheckCircle2,
    Clock3,
    ReceiptText,
    Search,
} from "lucide-react";
import type { FormEvent } from "react";
import { useState } from "react";
import InputError from "@/components/input-error";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { confirm, index as overview } from "@/routes/billing";
import { index as invoiceList } from "@/routes/billing/invoices";

type InvoiceStatus = "paid" | "unpaid" | "overdue" | "cancelled" | "refunded";

type Invoice = {
    id: number;
    invoice_number: string;
    status: InvoiceStatus;
    total: string;
    due_date: string;
    issued_at: string;
    paid_at: string | null;
    is_overdue: boolean;
    item_summary: string | null;
    item_count: number;
    client: { id: number; name: string; initials: string; email: string };
    currency: { code: string; symbol: string };
};

type Stats = {
    all: number;
    paid: number;
    unpaid: number;
    overdue: number;
    cancelled: number;
    refunded: number;
    last_30_days: number;
};

type Filters = {
    search?: string | null;
    status?: string | null;
    from?: string | null;
    to?: string | null;
    sort?: string;
    direction?: string;
};

type Pagination = {
    data: Invoice[];
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type BillingProps = {
    view: "overview" | "invoices";
    stats: Stats;
    moneyTotals?: Array<{
        code: string;
        symbol: string;
        total: string;
        average: string;
    }>;
    invoiceVolume?: Array<{ key: string; label: string; count: number }>;
    recentInvoices?: Invoice[];
    invoices?: Pagination;
    filters?: Filters;
};

const statusStyles: Record<InvoiceStatus, string> = {
    paid: "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300",
    unpaid: "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300",
    overdue:
        "border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300",
    cancelled:
        "border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300",
    refunded:
        "border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-300",
};

const railStyles: Record<InvoiceStatus, string> = {
    paid: "bg-emerald-500",
    unpaid: "bg-amber-500",
    overdue: "bg-red-500",
    cancelled: "bg-slate-400",
    refunded: "bg-violet-500",
};

function formatMoney(invoice: Invoice): string {
    return `${invoice.currency.code} ${Number(invoice.total).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function formatDate(date: string): string {
    return new Intl.DateTimeFormat("en", {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(new Date(`${date}T00:00:00`));
}

function PaymentForm({ invoice }: { invoice: Invoice }) {
    const form = useForm({ reference: "", received: false });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(confirm.url(invoice.id));
    }

    return (
        <form onSubmit={submit} className="grid gap-3 border-t p-4 sm:grid-cols-2">
            <div className="space-y-2 sm:col-span-2">
                <Label htmlFor={`reference-${invoice.id}`}>Payment reference</Label>
                <Input
                    id={`reference-${invoice.id}`}
                    required
                    maxLength={190}
                    value={form.data.reference}
                    onChange={(event) => form.setData("reference", event.target.value)}
                    placeholder="Bank transfer or receipt reference"
                />
                <InputError message={form.errors.reference} />
            </div>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    required
                    checked={form.data.received}
                    onChange={(event) => form.setData("received", event.target.checked)}
                />
                Full payment received
            </label>
            <Button className="sm:justify-self-end" disabled={form.processing}>
                {form.processing ? "Confirming…" : "Confirm payment"}
            </Button>
            <InputError message={form.errors.received} />
            <InputError message={(form.errors as Record<string, string>).invoice} />
        </form>
    );
}

function InvoiceRow({
    invoice,
    paymentActions = false,
}: {
    invoice: Invoice;
    paymentActions?: boolean;
}) {
    return (
        <article className="group relative overflow-hidden border-b last:border-b-0">
            <span className={`absolute inset-y-3 left-0 w-0.5 ${railStyles[invoice.status]}`} />
            <div className="grid gap-4 px-5 py-4 pl-6 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">
                <div className="flex size-10 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white shadow-sm dark:bg-slate-100 dark:text-slate-900">
                    {invoice.client.initials}
                </div>
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-medium">{invoice.invoice_number}</p>
                        <span className="text-muted-foreground text-xs">{invoice.client.name}</span>
                    </div>
                    <p className="text-muted-foreground mt-1 truncate text-sm">
                        {invoice.item_summary ?? "Invoice"}
                        {invoice.item_count > 1 && ` +${invoice.item_count - 1} more`}
                    </p>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Issued {formatDate(invoice.issued_at)} · Due {formatDate(invoice.due_date)}
                    </p>
                </div>
                <div className="flex items-center justify-between gap-4 sm:block sm:text-right">
                    <p className="font-semibold tabular-nums">{formatMoney(invoice)}</p>
                    <Badge
                        variant="outline"
                        className={`mt-1 capitalize ${statusStyles[invoice.status]}`}
                    >
                        {invoice.status === "paid" ? (
                            <CheckCircle2 />
                        ) : invoice.status === "overdue" ? (
                            <AlertCircle />
                        ) : (
                            <Clock3 />
                        )}
                        {invoice.status}
                    </Badge>
                </div>
            </div>
            {paymentActions && ["unpaid", "overdue"].includes(invoice.status) && (
                <details className="bg-muted/25">
                    <summary className="text-muted-foreground cursor-pointer px-6 py-2 text-sm hover:text-foreground">
                        Record payment
                    </summary>
                    <PaymentForm invoice={invoice} />
                </details>
            )}
        </article>
    );
}

function BillingTabs({ view }: { view: BillingProps["view"] }) {
    const tabClass = (active: boolean) =>
        `border-b-2 px-1 pb-3 text-sm font-medium transition-colors ${
            active
                ? "border-slate-900 text-foreground dark:border-slate-100"
                : "border-transparent text-muted-foreground hover:text-foreground"
        }`;

    return (
        <nav className="flex gap-7 border-b" aria-label="Billing views">
            <Link href={overview()} className={tabClass(view === "overview")}>
                Overview
            </Link>
            <Link href={invoiceList()} className={tabClass(view === "invoices")}>
                All invoices
            </Link>
        </nav>
    );
}

function StatCard({
    label,
    value,
    tone = "default",
}: {
    label: string;
    value: number;
    tone?: "default" | "danger";
}) {
    return (
        <div className="rounded-lg border bg-muted/30 p-4">
            <p className="text-muted-foreground text-sm">{label}</p>
            <p
                className={`mt-3 text-3xl font-light tabular-nums ${tone === "danger" ? "text-red-600 dark:text-red-400" : ""}`}
            >
                {value}
            </p>
        </div>
    );
}

function OverviewPanel({
    stats,
    moneyTotals = [],
    invoiceVolume = [],
    recentInvoices = [],
}: BillingProps) {
    const maxVolume = Math.max(...invoiceVolume.map((month) => month.count), 1);

    return (
        <div className="grid gap-8 xl:grid-cols-[minmax(0,0.9fr)_minmax(420px,1.25fr)]">
            <div className="space-y-6">
                <section>
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-wide">
                                Invoice stats
                            </p>
                            <p className="text-muted-foreground mt-1 text-xs">
                                All recorded invoices
                            </p>
                        </div>
                        <ReceiptText className="text-muted-foreground size-5" />
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <StatCard label="Total invoices" value={stats.all} />
                        <StatCard label="Paid invoices" value={stats.paid} />
                        <StatCard label="Awaiting payment" value={stats.unpaid} />
                        <StatCard label="Overdue invoices" value={stats.overdue} tone="danger" />
                    </div>
                </section>

                <section className="rounded-xl border p-5">
                    <div className="flex items-center gap-2">
                        <CalendarDays className="text-muted-foreground size-4" />
                        <h2 className="font-semibold">Invoice volume</h2>
                    </div>
                    <div className="mt-6 flex h-44 items-end gap-3">
                        {invoiceVolume.map((month) => (
                            <div
                                key={month.key}
                                className="flex flex-1 flex-col items-center gap-2"
                            >
                                <span className="text-muted-foreground text-xs tabular-nums">
                                    {month.count}
                                </span>
                                <div
                                    className="w-full min-w-5 rounded-t bg-sky-500/80 transition-all"
                                    style={{
                                        height: `${Math.max((month.count / maxVolume) * 112, 4)}px`,
                                    }}
                                />
                                <span className="text-muted-foreground text-xs">{month.label}</span>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="rounded-xl border p-5">
                    <h2 className="font-semibold">Totals by currency</h2>
                    <div className="mt-4 divide-y">
                        {moneyTotals.map((money) => (
                            <div key={money.code} className="grid grid-cols-2 gap-4 py-3 text-sm">
                                <div>
                                    <p className="text-muted-foreground">Total invoiced</p>
                                    <p className="mt-1 font-semibold">
                                        {money.code} {Number(money.total).toLocaleString()}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Average invoice</p>
                                    <p className="mt-1 font-semibold">
                                        {money.code} {Number(money.average).toLocaleString()}
                                    </p>
                                </div>
                            </div>
                        ))}
                        {moneyTotals.length === 0 && (
                            <p className="text-muted-foreground py-5 text-sm">
                                No invoice totals yet.
                            </p>
                        )}
                    </div>
                </section>
            </div>

            <section>
                <div className="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <p className="text-sm font-semibold uppercase tracking-wide">
                            Recent invoices
                        </p>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Latest billing activity
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={invoiceList()}>View all</Link>
                    </Button>
                </div>
                <div className="overflow-hidden rounded-xl border bg-background">
                    {recentInvoices.map((invoice) => (
                        <InvoiceRow key={invoice.id} invoice={invoice} />
                    ))}
                    {recentInvoices.length === 0 && (
                        <div className="p-12 text-center">
                            <ReceiptText className="text-muted-foreground mx-auto size-8" />
                            <p className="mt-3 font-medium">No invoices yet</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                New invoices will appear here.
                            </p>
                        </div>
                    )}
                </div>
            </section>
        </div>
    );
}

function InvoicesPanel({ stats, invoices, filters = {} }: BillingProps) {
    const [search, setSearch] = useState(filters.search ?? "");
    const quickFilters: Array<{ label: string; value: string; count: number }> = [
        { label: "All", value: "", count: stats.all },
        { label: "Paid", value: "paid", count: stats.paid },
        { label: "Unpaid", value: "unpaid", count: stats.unpaid },
        { label: "Overdue", value: "overdue", count: stats.overdue },
        { label: "Cancelled", value: "cancelled", count: stats.cancelled },
        { label: "Refunded", value: "refunded", count: stats.refunded },
        { label: "Last 30 days", value: "last_30_days", count: stats.last_30_days },
    ];

    function applyFilters(next: Partial<Filters>) {
        router.get(
            invoiceList(),
            { ...filters, ...next, page: undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    if (!invoices) {
        return null;
    }

    return (
        <div className="grid gap-6 lg:grid-cols-[210px_minmax(0,1fr)]">
            <aside>
                <p className="bg-muted/60 rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide">
                    Quick filters
                </p>
                <div className="mt-3 flex gap-2 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible">
                    {quickFilters.map((filter) => {
                        const active = (filters.status ?? "") === filter.value;

                        return (
                            <button
                                key={filter.label}
                                type="button"
                                onClick={() => applyFilters({ status: filter.value || null })}
                                className={`flex shrink-0 items-center justify-between gap-4 rounded-md px-3 py-2 text-left text-sm transition-colors ${
                                    active
                                        ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900"
                                        : "text-muted-foreground hover:bg-muted hover:text-foreground"
                                }`}
                            >
                                <span>{filter.label}</span>
                                <span className="text-xs tabular-nums opacity-70">
                                    {filter.count}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </aside>

            <section className="min-w-0 space-y-4">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyFilters({ search: search || null });
                    }}
                    className="relative"
                >
                    <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <Input
                        aria-label="Search invoices"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search by invoice number, client, company or email…"
                        className="h-11 pl-10"
                    />
                </form>

                <div className="grid gap-3 rounded-lg border bg-muted/20 p-3 md:grid-cols-[1fr_1fr_auto_auto]">
                    <Input
                        type="date"
                        aria-label="Issued from"
                        value={filters.from ?? ""}
                        onChange={(event) => applyFilters({ from: event.target.value || null })}
                    />
                    <Input
                        type="date"
                        aria-label="Issued to"
                        value={filters.to ?? ""}
                        onChange={(event) => applyFilters({ to: event.target.value || null })}
                    />
                    <select
                        aria-label="Sort invoices"
                        value={filters.sort ?? "issued"}
                        onChange={(event) => applyFilters({ sort: event.target.value })}
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="issued">Date issued</option>
                        <option value="due">Due date</option>
                        <option value="amount">Amount</option>
                        <option value="invoice">Invoice number</option>
                    </select>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label="Reverse sort order"
                        onClick={() =>
                            applyFilters({
                                direction: filters.direction === "asc" ? "desc" : "asc",
                            })
                        }
                    >
                        <ArrowDownUp />
                    </Button>
                </div>

                <div className="flex items-center justify-between gap-4">
                    <p className="text-muted-foreground text-sm">
                        {invoices.total === 0
                            ? "No invoices"
                            : `Showing ${invoices.from}–${invoices.to} of ${invoices.total}`}
                    </p>
                    {(filters.search || filters.from || filters.to || filters.status) && (
                        <Button variant="ghost" size="sm" onClick={() => router.get(invoiceList())}>
                            Clear filters
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border bg-background">
                    {invoices.data.map((invoice) => (
                        <InvoiceRow key={invoice.id} invoice={invoice} paymentActions />
                    ))}
                    {invoices.data.length === 0 && (
                        <div className="p-12 text-center">
                            <Search className="text-muted-foreground mx-auto size-8" />
                            <p className="mt-3 font-medium">No invoices found</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Try another search or filter.
                            </p>
                        </div>
                    )}
                </div>

                <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" asChild disabled={!invoices.prev_page_url}>
                        {invoices.prev_page_url ? (
                            <Link href={invoices.prev_page_url}>Previous</Link>
                        ) : (
                            <span>Previous</span>
                        )}
                    </Button>
                    <Button variant="outline" size="sm" asChild disabled={!invoices.next_page_url}>
                        {invoices.next_page_url ? (
                            <Link href={invoices.next_page_url}>Next</Link>
                        ) : (
                            <span>Next</span>
                        )}
                    </Button>
                </div>
            </section>
        </div>
    );
}

export default function Billing(props: BillingProps) {
    return (
        <>
            <Head title="Invoices" />
            <div className="mx-auto w-full max-w-7xl space-y-7 p-6 lg:p-8">
                <header>
                    <p className="text-muted-foreground text-sm">Billing</p>
                    <h1 className="mt-1 text-3xl font-light tracking-tight">Invoices</h1>
                    <p className="text-muted-foreground mt-2 max-w-2xl text-sm">
                        Monitor invoice health, find billing records and record received payments.
                    </p>
                </header>
                <BillingTabs view={props.view} />
                {props.view === "overview" ? (
                    <OverviewPanel {...props} />
                ) : (
                    <InvoicesPanel {...props} />
                )}
            </div>
        </>
    );
}
