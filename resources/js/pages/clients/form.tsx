import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, UserRound } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as clientsIndex, show as clientShow, store, update } from '@/routes/clients';

type Client = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    company_name: string | null;
    address1: string | null;
    address2: string | null;
    city: string | null;
    state: string | null;
    postcode: string | null;
    country: string | null;
    currency_id: number | null;
    status: 'active' | 'inactive' | 'closed';
    admin_notes: string | null;
};

type Currency = { id: number; code: string; symbol: string };

type Props = { client: Client | null; currencies: Currency[] };

export default function ClientForm({ client, currencies }: Props) {
    const editing = client !== null;
    const form = useForm({
        first_name: client?.first_name ?? '',
        last_name: client?.last_name ?? '',
        email: client?.email ?? '',
        phone: client?.phone ?? '',
        company_name: client?.company_name ?? '',
        address1: client?.address1 ?? '',
        address2: client?.address2 ?? '',
        city: client?.city ?? '',
        state: client?.state ?? '',
        postcode: client?.postcode ?? '',
        country: client?.country ?? '',
        currency_id: client?.currency_id?.toString() ?? '',
        status: client?.status ?? 'active',
        admin_notes: client?.admin_notes ?? '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { onFinish: () => form.reset('password', 'password_confirmation') };
        if (editing) {
            form.patch(update.url(client.id), options);
        } else {
            form.post(store.url(), options);
        }
    }

    return (
        <>
            <Head title={editing ? `Edit ${client.first_name} ${client.last_name}` : 'Add client'} />
            <div className="mx-auto w-full max-w-5xl space-y-8 p-6 lg:p-10">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={editing ? clientShow(client.id) : clientsIndex()} aria-label="Go back">
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <div>
                        <p className="text-muted-foreground text-sm">Client management</p>
                        <h1 className="text-3xl font-light tracking-tight">
                            {editing ? 'Edit client' : 'Add a client'}
                        </h1>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <section className="rounded-2xl border bg-background p-6 shadow-sm">
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <UserRound className="size-5" />
                            </div>
                            <div>
                                <h2 className="font-semibold">Basic details</h2>
                                <p className="text-muted-foreground text-sm">The details shown across the admin and client portal.</p>
                            </div>
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="First name" name="first_name" value={form.data.first_name} error={form.errors.first_name} onChange={(value) => form.setData('first_name', value)} required />
                            <Field label="Last name" name="last_name" value={form.data.last_name} error={form.errors.last_name} onChange={(value) => form.setData('last_name', value)} required />
                            <Field label="Email address" name="email" type="email" value={form.data.email} error={form.errors.email} onChange={(value) => form.setData('email', value)} required />
                            <Field label="Phone" name="phone" value={form.data.phone} error={form.errors.phone} onChange={(value) => form.setData('phone', value)} />
                            <Field label="Company" name="company_name" value={form.data.company_name} error={form.errors.company_name} onChange={(value) => form.setData('company_name', value)} />
                            <div className="space-y-2">
                                <Label htmlFor="currency_id">Currency</Label>
                                <select id="currency_id" value={form.data.currency_id} onChange={(event) => form.setData('currency_id', event.target.value)} className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm">
                                    <option value="">Select currency</option>
                                    {currencies.map((currency) => <option key={currency.id} value={currency.id}>{currency.code} ({currency.symbol})</option>)}
                                </select>
                                <InputError message={form.errors.currency_id} />
                            </div>
                        </div>
                    </section>

                    <section className="rounded-2xl border bg-background p-6 shadow-sm">
                        <h2 className="font-semibold">Billing address</h2>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <div className="space-y-2 sm:col-span-2"><Label htmlFor="address1">Address line 1</Label><Input id="address1" value={form.data.address1} onChange={(event) => form.setData('address1', event.target.value)} /><InputError message={form.errors.address1} /></div>
                            <Field label="Address line 2" name="address2" value={form.data.address2} error={form.errors.address2} onChange={(value) => form.setData('address2', value)} />
                            <Field label="City" name="city" value={form.data.city} error={form.errors.city} onChange={(value) => form.setData('city', value)} />
                            <Field label="State / region" name="state" value={form.data.state} error={form.errors.state} onChange={(value) => form.setData('state', value)} />
                            <Field label="Postcode" name="postcode" value={form.data.postcode} error={form.errors.postcode} onChange={(value) => form.setData('postcode', value)} />
                            <Field label="Country code" name="country" value={form.data.country} error={form.errors.country} onChange={(value) => form.setData('country', value.toUpperCase())} maxLength={2} placeholder="BD" />
                        </div>
                    </section>

                    <section className="rounded-2xl border bg-background p-6 shadow-sm">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="space-y-2"><Label htmlFor="status">Account status</Label><select id="status" value={form.data.status} onChange={(event) => form.setData('status', event.target.value as Client['status'])} className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"><option value="active">Active</option><option value="inactive">Inactive</option><option value="closed">Closed</option></select><InputError message={form.errors.status} /></div>
                            <div className="space-y-2"><Label htmlFor="password">{editing ? 'New password (optional)' : 'Temporary password'}</Label><Input id="password" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} /><InputError message={form.errors.password} /></div>
                            <div className="space-y-2 sm:col-span-2"><Label htmlFor="password_confirmation">Confirm password</Label><Input id="password_confirmation" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} /><InputError message={form.errors.password_confirmation} /></div>
                            <div className="space-y-2 sm:col-span-2"><Label htmlFor="admin_notes">Internal notes</Label><textarea id="admin_notes" value={form.data.admin_notes} onChange={(event) => form.setData('admin_notes', event.target.value)} placeholder="Only your team can see these notes." className="border-input bg-background min-h-28 w-full rounded-md border p-3 text-sm" /><InputError message={form.errors.admin_notes} /></div>
                        </div>
                    </section>

                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="outline" asChild><Link href={editing ? clientShow(client.id) : clientsIndex()}>Cancel</Link></Button>
                        <Button type="submit" disabled={form.processing}><Check /> {editing ? 'Save changes' : 'Create client'}</Button>
                    </div>
                </form>
            </div>
        </>
    );
}

function Field({ label, name, value, error, onChange, type = 'text', required = false, maxLength, placeholder }: { label: string; name: string; value: string; error?: string; onChange: (value: string) => void; type?: string; required?: boolean; maxLength?: number; placeholder?: string }) {
    return <div className="space-y-2"><Label htmlFor={name}>{label}</Label><Input id={name} name={name} type={type} value={value} required={required} maxLength={maxLength} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} /><InputError message={error} /></div>;
}
