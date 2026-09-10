import { Head, Link } from '@inertiajs/react';
import { create } from '@/routes/client/orders';
import { show } from '@/routes/client/invoices';
import { index as tickets } from '@/routes/client/tickets';

type Service = {
    id: number;
    domain: string | null;
    status: string;
    provisioning_status: string;
    product: { name: string };
};
type Invoice = {
    id: number;
    invoice_number: string;
    status: string;
    total: string;
    currency: { code: string };
};
export default function ClientDashboard({
    services,
    invoices,
    openTickets,
}: {
    services: Service[];
    invoices: Invoice[];
    openTickets: number;
}) {
    return (
        <>
            <Head title="Your services" />
            <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-2xl font-semibold">Your services</h1>
                <Link
                    href={create()}
                    className="bg-primary text-primary-foreground rounded-lg px-4 py-2 text-sm"
                >
                    Order hosting
                </Link>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                {services.length === 0 && (
                    <p className="text-muted-foreground">
                        Your hosting services will appear here after payment
                        confirmation.
                    </p>
                )}
                {services.map((service) => (
                    <section key={service.id} className="rounded-xl border p-5">
                        <h2 className="font-semibold">
                            {service.product.name}
                        </h2>
                        <p className="text-muted-foreground mt-2">
                            {service.domain}
                        </p>
                        <p className="mt-4 text-sm">
                            {service.status === 'pending'
                                ? ['failed', 'needs_review'].includes(
                                      service.provisioning_status,
                                  )
                                    ? 'Activation needs staff attention'
                                    : 'Activation in progress'
                                : service.status}
                        </p>
                    </section>
                ))}
            </div>
            <div className="mt-10 flex flex-wrap items-center justify-between gap-4">
                <h2 className="text-xl font-semibold">Support</h2>
                <Link
                    href={tickets()}
                    className="text-primary text-sm underline-offset-4 hover:underline"
                >
                    {openTickets > 0
                        ? `${openTickets} open ticket${openTickets === 1 ? '' : 's'}`
                        : 'No open tickets'}
                </Link>
            </div>
            <h2 className="mt-6 mb-4 text-xl font-semibold">
                Recent invoices
            </h2>
            <div className="divide-y rounded-xl border">
                {invoices.length === 0 && (
                    <p className="text-muted-foreground p-5">
                        No invoices yet.
                    </p>
                )}
                {invoices.map((invoice) => (
                    <Link
                        key={invoice.id}
                        href={show(invoice.id)}
                        className="hover:bg-muted flex flex-wrap justify-between gap-3 p-5"
                    >
                        <span className="break-all">
                            {invoice.invoice_number}
                        </span>
                        <span>
                            {invoice.currency.code} {invoice.total} ·{' '}
                            {invoice.status}
                        </span>
                    </Link>
                ))}
            </div>
        </>
    );
}
