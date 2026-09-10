import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/client/orders';

type Price = {
    id: number;
    billing_cycle: string;
    price: string;
    setup_fee: string;
    currency: string;
};
type Product = {
    id: number;
    name: string;
    description: string | null;
    pricing: Price[];
};
export default function Order({
    products,
    checkoutToken,
}: {
    products: Product[];
    checkoutToken: string;
}) {
    const form = useForm({
        pricing_id: '',
        domain: '',
        checkout_token: checkoutToken,
    });
    const selected = products
        .flatMap((product) => product.pricing)
        .find((price) => String(price.id) === form.data.pricing_id);
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(store.url());
    }
    return (
        <>
            <Head title="Order hosting" />
            <div className="mb-8">
                <h1 className="text-2xl font-semibold">Choose your hosting</h1>
                <p className="text-muted-foreground mt-2">
                    Use a domain you already own. Hosting activates after your
                    payment is confirmed.
                </p>
            </div>
            {products.length === 0 ? (
                <p>
                    No hosting packages are available yet. Please contact
                    support.
                </p>
            ) : (
                <form onSubmit={submit} className="space-y-8">
                    <fieldset className="grid gap-4 md:grid-cols-2">
                        <legend className="sr-only">
                            Hosting package and billing cycle
                        </legend>
                        {products.map((product) => (
                            <section
                                key={product.id}
                                className="rounded-xl border p-6"
                            >
                                <h2 className="text-lg font-semibold">
                                    {product.name}
                                </h2>
                                {product.description && (
                                    <p className="text-muted-foreground mt-2 text-sm">
                                        {product.description}
                                    </p>
                                )}
                                <div className="mt-5 space-y-3">
                                    {product.pricing.map((price) => (
                                        <label
                                            key={price.id}
                                            className="flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                                        >
                                            <input
                                                type="radio"
                                                name="pricing_id"
                                                value={price.id}
                                                checked={
                                                    form.data.pricing_id ===
                                                    String(price.id)
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'pricing_id',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                                className="mt-1"
                                            />
                                            <span>
                                                <span className="font-medium">
                                                    {price.currency}{' '}
                                                    {price.price}
                                                </span>{' '}
                                                /{' '}
                                                {price.billing_cycle.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                                <span className="text-muted-foreground block text-sm">
                                                    Setup: {price.currency}{' '}
                                                    {price.setup_fee} once
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </section>
                        ))}
                    </fieldset>
                    <InputError message={form.errors.pricing_id} />
                    <div className="max-w-lg space-y-3">
                        <Label htmlFor="domain">Your domain</Label>
                        <Input
                            id="domain"
                            value={form.data.domain}
                            onChange={(e) =>
                                form.setData('domain', e.target.value)
                            }
                            placeholder="example.com"
                            required
                            maxLength={253}
                        />
                        <InputError message={form.errors.domain} />
                        {selected && (
                            <p className="text-muted-foreground text-sm">
                                Your invoice will include {selected.currency}{' '}
                                {selected.price} for hosting plus{' '}
                                {selected.currency} {selected.setup_fee} for
                                setup. Domain registration is not included.
                            </p>
                        )}
                        <InputError message={form.errors.checkout_token} />
                        <Button disabled={form.processing || !selected}>
                            {form.processing
                                ? 'Creating invoice…'
                                : 'Place order & view invoice'}
                        </Button>
                    </div>
                </form>
            )}
        </>
    );
}
