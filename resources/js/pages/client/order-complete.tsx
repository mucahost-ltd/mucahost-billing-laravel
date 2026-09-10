import { Head, Link } from '@inertiajs/react';
import { dashboard } from '@/routes/client';
export default function OrderComplete({ order }: { order: { order_number: string; status: string } }) {
    return <><Head title="Order received" /><section className="max-w-2xl rounded-xl border p-8"><h1 className="text-2xl font-semibold">Your order is confirmed</h1><p className="mt-3 break-all text-sm text-muted-foreground">{order.order_number}</p><p className="mt-6">This product is free. There is no invoice or payment required.</p><Link href={dashboard()} className="mt-6 inline-block rounded-lg bg-primary px-4 py-2 text-primary-foreground">View your services</Link></section></>;
}
