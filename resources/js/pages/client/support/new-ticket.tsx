import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/client/tickets';

type Department = { id: number; name: string };
type Service = {
    id: number;
    domain: string | null;
    product: { name: string };
};

export default function TicketsCreate({
    departments,
    services,
}: {
    departments: Department[];
    services: Service[];
}) {
    const form = useForm({
        department_id: '',
        service_id: '',
        subject: '',
        priority: 'low',
        message: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(store.url());
    }

    return (
        <>
            <Head title="Open support ticket" />
            <div className="mb-8">
                <h1 className="text-2xl font-semibold">Open a support ticket</h1>
                <p className="text-muted-foreground mt-2">
                    Describe your issue and we'll get back to you as soon as
                    possible.
                </p>
            </div>

            <form
                onSubmit={submit}
                className="max-w-2xl space-y-6 rounded-xl border p-6"
            >
                <div className="space-y-2">
                    <Label htmlFor="department">Department</Label>
                    <select
                        id="department"
                        required
                        value={form.data.department_id}
                        onChange={(e) =>
                            form.setData('department_id', e.target.value)
                        }
                        className="w-full rounded-md border bg-background p-3"
                    >
                        <option value="">Select a department…</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.department_id} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="service">Related service (optional)</Label>
                    <select
                        id="service"
                        value={form.data.service_id}
                        onChange={(e) =>
                            form.setData('service_id', e.target.value)
                        }
                        className="w-full rounded-md border bg-background p-3"
                    >
                        <option value="">No related service</option>
                        {services.map((service) => (
                            <option key={service.id} value={service.id}>
                                {service.product.name}
                                {service.domain ? ` — ${service.domain}` : ''}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.service_id} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="subject">Subject</Label>
                    <Input
                        id="subject"
                        required
                        value={form.data.subject}
                        onChange={(e) =>
                            form.setData('subject', e.target.value)
                        }
                        placeholder="Brief summary of your issue"
                    />
                    <InputError message={form.errors.subject} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="priority">Priority</Label>
                    <select
                        id="priority"
                        value={form.data.priority}
                        onChange={(e) =>
                            form.setData('priority', e.target.value)
                        }
                        className="w-full rounded-md border bg-background p-3"
                    >
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                    <InputError message={form.errors.priority} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="message">Message</Label>
                    <textarea
                        id="message"
                        required
                        rows={6}
                        value={form.data.message}
                        onChange={(e) =>
                            form.setData('message', e.target.value)
                        }
                        placeholder="Describe your issue in detail…"
                        className="min-h-32 w-full rounded-md border bg-background p-3"
                    />
                    <InputError message={form.errors.message} />
                </div>

                <div className="flex justify-end gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Open ticket
                    </Button>
                </div>
            </form>
        </>
    );
}
