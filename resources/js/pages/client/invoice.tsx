import { Head, Link } from '@inertiajs/react';
import { dashboard } from '@/routes/client';

type Invoice = {
    id: number;
    invoice_number: string;
    status: string;
    subtotal: string;
    tax: string;
    total: string;
    due_date: string;
    paid_at: string | null;
};
export default function InvoicePage({
    invoice,
    currency,
    items,
}: {
    invoice: Invoice;
    currency: string;
    items: { id: number; description: string; amount: string }[];
}) {
    return (
        <>
            <Head title={invoice.invoice_number} />
            <Link href={dashboard()} className="text-sm underline">
                Back to your services
            </Link>
            <article className="mt-6 max-w-3xl rounded-xl border p-6 sm:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Invoice</h1>
                        <p className="text-muted-foreground mt-2 text-sm break-all">
                            {invoice.invoice_number}
                        </p>
                    </div>
                    <span className="bg-muted rounded-full px-3 py-1 text-sm capitalize">
                        {invoice.status}
                    </span>
                </div>
                <p className="mt-6 text-sm">
                    Due {invoice.due_date.slice(0, 10)}
                </p>
                <dl className="my-6 divide-y border-y">
                    {items.map((item) => (
                        <div
                            key={item.id}
                            className="flex justify-between gap-6 py-4"
                        >
                            <dt>{item.description}</dt>
                            <dd className="whitespace-nowrap">
                                {currency} {item.amount}
                            </dd>
                        </div>
                    ))}
                </dl>
                <dl className="ml-auto max-w-xs space-y-2">
                    <div className="flex justify-between">
                        <dt>Subtotal</dt>
                        <dd>
                            {currency} {invoice.subtotal}
                        </dd>
                    </div>
                    <div className="flex justify-between">
                        <dt>Tax</dt>
                        <dd>
                            {currency} {invoice.tax}
                        </dd>
                    </div>
                    <div className="flex justify-between text-lg font-semibold">
                        <dt>Total</dt>
                        <dd>
                            {currency} {invoice.total}
                        </dd>
                    </div>
                </dl>
                <p className="bg-muted mt-8 rounded-lg p-4 text-sm">
                    {invoice.status === 'paid'
                        ? 'Payment confirmed. You can follow hosting activation in your dashboard.'
                        : 'Contact staff to arrange payment and quote this invoice number. Your hosting will activate after staff confirms receipt of the full payment.'}
                </p>
            </article>
        </>
    );
}
