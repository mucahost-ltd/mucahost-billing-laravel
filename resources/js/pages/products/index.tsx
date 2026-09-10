import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { create, edit } from '@/routes/products';
import { store, update, destroy } from '@/routes/product-groups';

type Product = { id: number; name: string; type: string; billing_type: string; is_active: boolean; is_visible: boolean; services_count: number; module: string; setup_mode: string; pricing: { id: number; price: string; billing_cycle: string; currency: { code: string } }[] };
type Group = { id: number; name: string; slug: string; description: string | null; sort_order: number; is_visible: boolean; products: Product[] };
function GroupForm({ group, done }: { group: Group | null; done: () => void }) {
    const form = useForm({ name: group?.name ?? '', slug: group?.slug ?? '', description: group?.description ?? '', sort_order: group?.sort_order ?? 0, is_visible: group?.is_visible ?? true });
    return <form onSubmit={event => { event.preventDefault(); if (group) { form.patch(update.url(group.id), { onSuccess: done }); } else { form.post(store.url(), { onSuccess: done }); } }} className="space-y-4">
        <div className="space-y-2"><Label htmlFor="group-name">Group name</Label><Input id="group-name" required value={form.data.name} onChange={e => form.setData('name', e.target.value)} /><InputError message={form.errors.name} /></div>
        <div className="space-y-2"><Label htmlFor="group-slug">Slug</Label><Input id="group-slug" required value={form.data.slug} placeholder="shared-hosting" onChange={e => form.setData('slug', e.target.value)} /><InputError message={form.errors.slug} /></div>
        <div className="space-y-2"><Label htmlFor="group-description">Description</Label><textarea id="group-description" className="min-h-24 w-full rounded-md border bg-background p-3" value={form.data.description} onChange={e => form.setData('description', e.target.value)} /></div>
        <div className="space-y-2"><Label htmlFor="group-sort">Display order</Label><Input id="group-sort" type="number" min="0" value={form.data.sort_order} onChange={e => form.setData('sort_order', Number(e.target.value))} /></div>
        <label className="flex gap-2 text-sm"><input type="checkbox" checked={form.data.is_visible} onChange={e => form.setData('is_visible', e.target.checked)} />Show this group in the storefront</label>
        <div className="flex justify-end gap-2"><Button type="button" variant="outline" onClick={done}>Cancel</Button><Button disabled={form.processing}>Save group</Button></div>
    </form>;
}
export default function Products({ groups }: { groups: Group[] }) {
    const [query, setQuery] = useState('');
    const [editing, setEditing] = useState<Group | null | undefined>(undefined);
    const [deleting, setDeleting] = useState<Group | null>(null);
    const deletion = useForm({});
    const errors = usePage().props.errors as Record<string, string>;
    return <><Head title="Products & services" /><div className="mx-auto w-full max-w-7xl space-y-6 p-6">
        <header className="flex flex-wrap items-start justify-between gap-4"><div><h1 className="text-2xl font-semibold">Products & services</h1><p className="mt-2 text-muted-foreground">Manage what you sell, what you charge, and how orders are fulfilled.</p></div><div className="flex gap-2"><Button variant="outline" onClick={() => setEditing(null)}>Create group</Button><Button asChild><Link href={create()}>Create product</Link></Button></div></header>
        <Input aria-label="Search products" placeholder="Search products or groups…" value={query} onChange={e => setQuery(e.target.value)} className="max-w-sm" /><InputError message={errors.product || errors.group} />
        {groups.length === 0 && <div className="rounded-xl border border-dashed p-12 text-center"><h2 className="font-semibold">Start with a product group</h2><p className="mt-2 text-muted-foreground">For example, Shared Hosting, Servers, or Professional Services.</p><Button className="mt-5" onClick={() => setEditing(null)}>Create your first group</Button></div>}
        {groups.map(group => {
            const products = group.products.filter(p => `${group.name} ${p.name}`.toLowerCase().includes(query.toLowerCase()));
            if (query && products.length === 0 && !group.name.toLowerCase().includes(query.toLowerCase())) { return null; }
            return <section key={group.id} className="overflow-hidden rounded-xl border"><div className="flex flex-wrap items-center justify-between gap-3 bg-muted/40 px-5 py-4"><div><h2 className="font-semibold">{group.name} <span className="ml-2 text-sm font-normal text-muted-foreground">{group.products.length} products · {group.is_visible ? 'Visible' : 'Hidden'}</span></h2>{group.description && <p className="mt-1 text-sm text-muted-foreground">{group.description}</p>}</div><div className="flex gap-2"><Button size="sm" variant="outline" onClick={() => setEditing(group)}>Edit group</Button><Button size="sm" variant="ghost" onClick={() => setDeleting(group)}>Delete</Button></div></div>
                <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="border-b text-muted-foreground"><tr>{['Product', 'Payment type', 'Prices', 'Provisioning', 'Services', 'Visibility'].map(label => <th key={label} className="px-5 py-3 font-medium">{label}</th>)}</tr></thead><tbody className="divide-y">{products.map(product => <tr key={product.id}><td className="px-5 py-4"><Link href={edit(product.id)} className="font-medium underline-offset-4 hover:underline">{product.name}</Link><p className="mt-1 text-xs text-muted-foreground">{product.type.replaceAll('_', ' ')}</p></td><td className="px-5 py-4 capitalize">{product.billing_type.replace('_', ' ')}</td><td className="px-5 py-4">{product.billing_type === 'free' ? 'Free' : product.pricing.map(price => <div key={price.id} className="whitespace-nowrap">{price.currency.code} {price.price} / {price.billing_cycle.replace('_', ' ')}</div>)}</td><td className="px-5 py-4 capitalize">{product.module}<p className="mt-1 text-xs text-muted-foreground">{product.setup_mode.replaceAll('_', ' ')}</p></td><td className="px-5 py-4">{product.services_count}</td><td className="px-5 py-4">{!product.is_active ? 'Disabled' : product.is_visible ? 'Visible' : 'Hidden'}</td></tr>)}</tbody></table>{products.length === 0 && <p className="p-5 text-sm text-muted-foreground">No products in this group yet.</p>}</div>
            </section>;
        })}
        <Dialog open={editing !== undefined} onOpenChange={open => { if (!open) { setEditing(undefined); } }}><DialogContent><DialogHeader><DialogTitle>{editing ? 'Edit group' : 'Create group'}</DialogTitle></DialogHeader>{editing !== undefined && <GroupForm key={editing?.id ?? 'new'} group={editing} done={() => setEditing(undefined)} />}</DialogContent></Dialog>
        <Dialog open={deleting !== null} onOpenChange={open => { if (!open) { setDeleting(null); } }}><DialogContent><DialogHeader><DialogTitle>Delete {deleting?.name}?</DialogTitle></DialogHeader><p className="text-sm text-muted-foreground">Only empty groups can be deleted. Products and billing history are protected.</p><InputError message={(deletion.errors as Record<string, string>).group} /><Button variant="destructive" disabled={deletion.processing} onClick={() => { if (deleting) { deletion.delete(destroy.url(deleting.id), { onSuccess: () => setDeleting(null) }); } }}>Delete group</Button></DialogContent></Dialog>
    </div></>;
}
